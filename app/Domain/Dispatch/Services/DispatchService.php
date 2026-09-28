<?php

namespace App\Domain\Dispatch\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Dispatch\Exceptions\DispatchConflict;
use App\Events\DispatchStateChanged;
use App\Models\CaseEvent;
use App\Models\CaseVehicleAssignment;
use App\Models\EmergencyCase;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CorrelationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchService
{
    public function __construct(private AuditRecorder $audit, private CorrelationContext $correlation) {}

    public function createCase(Organization $org, User $actor, array $data): EmergencyCase
    {
        if ($key = $data['idempotency_key'] ?? null) {
            $existing = EmergencyCase::forOrganization($org)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
        }
        $case = DB::transaction(function () use ($org, $actor, $data) {
            $seq = DB::selectOne("SELECT nextval('emergency_case_number_seq') AS value")->value;
            $case = EmergencyCase::create([...$data, 'case_number' => sprintf('MG-%s-%06d', now()->format('Y'), $seq), 'organization_id' => $org->id, 'source' => $data['source'] ?? EmergencyCaseSource::Manual, 'status' => EmergencyCaseStatus::Received, 'priority' => $data['priority'] ?? EmergencyCasePriority::Unknown, 'received_at' => now(), 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->timeline($case, $actor, 'CaseCreated', 'Emergency case received', ['source' => $case->source->value]);
            $this->audit->record('case.created', $case, $org, ['case_number' => $case->case_number], actor: $actor);

            return $case;
        });
        $this->broadcast($case, 'case.created');

        return $case;
    }

    public function triage(EmergencyCase $case, User $actor, EmergencyCasePriority $priority, ?string $notes): EmergencyCase
    {
        $case = DB::transaction(function () use ($case, $actor, $priority, $notes) {
            $locked = EmergencyCase::lockForUpdate()->findOrFail($case->id);
            if (! in_array($locked->status, [EmergencyCaseStatus::Received, EmergencyCaseStatus::Triaged], true)) {
                throw new DispatchConflict('Case cannot be triaged in its current state.');
            }$old = $locked->priority;
            $locked->update(['priority' => $priority, 'dispatcher_notes' => $notes, 'status' => EmergencyCaseStatus::Triaged, 'triaged_at' => $locked->triaged_at ?? now(), 'updated_by' => $actor->id, 'version' => $locked->version + 1]);
            $this->timeline($locked, $actor, $old === $priority ? 'CaseTriaged' : 'CasePriorityChanged', 'Dispatch triage recorded', ['priority' => $priority->value]);
            $this->audit->record('case.triaged', $locked, $locked->organization, ['priority' => $priority->value], actor: $actor);

            return $locked;
        });
        $this->broadcast($case, 'case.triaged');

        return $case;
    }

    public function assign(EmergencyCase $case, Vehicle $vehicle, User $actor, ?string $key = null): CaseVehicleAssignment
    {
        if ($key && ($found = CaseVehicleAssignment::where('organization_id', $case->organization_id)->where('idempotency_key', $key)->first())) {
            return $found;
        }
        $assignment = DB::transaction(function () use ($case, $vehicle, $actor, $key) {
            $lockedCase = EmergencyCase::lockForUpdate()->findOrFail($case->id);
            $lockedVehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            if ($lockedCase->organization_id !== $lockedVehicle->organization_id) {
                throw new DispatchConflict('Case and vehicle must belong to the same organization.');
            }if ($lockedCase->status !== EmergencyCaseStatus::Triaged) {
                throw new DispatchConflict('Only triaged cases can receive a unit.');
            }if (! $lockedVehicle->is_active || $lockedVehicle->status !== VehicleStatus::Available) {
                throw new DispatchConflict('Vehicle is not available.');
            }$assignment = CaseVehicleAssignment::create(['organization_id' => $lockedCase->organization_id, 'emergency_case_id' => $lockedCase->id, 'vehicle_id' => $lockedVehicle->id, 'status' => AssignmentStatus::Pending, 'idempotency_key' => $key, 'assigned_by' => $actor->id, 'assigned_at' => now()]);
            $lockedCase->update(['status' => EmergencyCaseStatus::UnitAssigned, 'assigned_at' => now(), 'updated_by' => $actor->id, 'version' => $lockedCase->version + 1]);
            $lockedVehicle->update(['status' => VehicleStatus::Assigned]);
            $this->timeline($lockedCase, $actor, 'UnitAssigned', 'Unit '.$lockedVehicle->callsign.' assigned', ['vehicle_id' => $lockedVehicle->id, 'assignment_id' => $assignment->id, 'delivery_status' => 'PENDING']);
            $this->audit->record('assignment.created', $assignment, $lockedCase->organization, ['case_id' => $lockedCase->id, 'vehicle_id' => $lockedVehicle->id], actor: $actor);

            return $assignment;
        });
        $this->broadcast($assignment->emergencyCase, 'unit.assigned', $assignment->vehicle_id, ['assignment_id' => $assignment->id, 'delivery_status' => 'PENDING']);

        return $assignment;
    }

    public function deliver(CaseVehicleAssignment $assignment, User $actor): CaseVehicleAssignment
    {
        if (in_array($assignment->status, [AssignmentStatus::Delivered, AssignmentStatus::Accepted], true)) {
            return $assignment;
        }
        $assignment = DB::transaction(function () use ($assignment, $actor) {
            $a = CaseVehicleAssignment::lockForUpdate()->findOrFail($assignment->id);
            if ($a->status !== AssignmentStatus::Pending) {
                throw new DispatchConflict('Mission is not pending delivery.');
            }$a->update(['status' => AssignmentStatus::Delivered, 'delivered_by' => $actor->id, 'delivered_at' => now()]);
            $this->timeline($a->emergencyCase, $actor, 'MissionDelivered', 'Mission displayed to assigned crew', ['assignment_id' => $a->id]);
            $this->audit->record('assignment.delivered', $a, $a->organization, actor: $actor);

            return $a;
        });
        $this->broadcast($assignment->emergencyCase, 'mission.delivered', $assignment->vehicle_id, ['assignment_id' => $assignment->id]);

        return $assignment;
    }

    public function accept(CaseVehicleAssignment $assignment, User $actor): CaseVehicleAssignment
    {
        if ($assignment->status === AssignmentStatus::Accepted && $assignment->accepted_by === $actor->id) {
            return $assignment;
        }
        $assignment = DB::transaction(function () use ($assignment, $actor) {
            $a = CaseVehicleAssignment::lockForUpdate()->findOrFail($assignment->id);
            $activeCrew = $a->vehicle->crewAssignments()->where('user_id', $actor->id)->whereNull('ended_at')->exists();
            if (! $activeCrew) {
                throw new DispatchConflict('Only active crew of the assigned vehicle can accept this mission.');
            }if ($a->status === AssignmentStatus::Accepted) {
                if ($a->accepted_by === $actor->id) {
                    return $a;
                }throw new DispatchConflict('Mission was accepted by another crew member.');
            }if (! in_array($a->status, [AssignmentStatus::Pending, AssignmentStatus::Delivered], true)) {
                throw new DispatchConflict('Mission is no longer active.');
            }if ($a->status === AssignmentStatus::Pending) {
                $a->delivered_by = $actor->id;
                $a->delivered_at = now();
            }$a->status = AssignmentStatus::Accepted;
            $a->accepted_by = $actor->id;
            $a->accepted_at = now();
            $a->save();
            $case = EmergencyCase::lockForUpdate()->findOrFail($a->emergency_case_id);
            $case->update(['status' => EmergencyCaseStatus::UnitAccepted, 'updated_by' => $actor->id, 'version' => $case->version + 1]);
            $this->timeline($case, $actor, 'MissionAccepted', 'Mission accepted by assigned crew', ['assignment_id' => $a->id]);
            $this->audit->record('assignment.accepted', $a, $a->organization, actor: $actor);

            return $a;
        });
        $this->broadcast($assignment->emergencyCase, 'mission.accepted', $assignment->vehicle_id, ['assignment_id' => $assignment->id]);

        return $assignment;
    }

    public function cancelAssignment(CaseVehicleAssignment $assignment, User $actor, string $reason): CaseVehicleAssignment
    {
        $assignment = DB::transaction(function () use ($assignment, $actor, $reason) {
            $a = CaseVehicleAssignment::lockForUpdate()->findOrFail($assignment->id);
            if (! $a->status->isActive()) {
                throw new DispatchConflict('Mission is no longer active.');
            }
            $case = EmergencyCase::lockForUpdate()->findOrFail($a->emergency_case_id);
            $vehicle = Vehicle::lockForUpdate()->findOrFail($a->vehicle_id);
            $a->update(['status' => AssignmentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $vehicle->update(['status' => VehicleStatus::Available]);
            $case->update(['status' => EmergencyCaseStatus::Triaged, 'assigned_at' => null, 'updated_by' => $actor->id, 'version' => $case->version + 1]);
            $this->timeline($case, $actor, 'UnitAssignmentCancelled', 'Unit assignment cancelled', ['assignment_id' => $a->id, 'reason' => $reason]);
            $this->audit->record('assignment.cancelled', $a, $a->organization, ['reason' => $reason], actor: $actor);

            return $a;
        });
        $this->broadcast($assignment->emergencyCase, 'assignment.cancelled', $assignment->vehicle_id, ['assignment_id' => $assignment->id]);

        return $assignment;
    }

    public function reassign(CaseVehicleAssignment $assignment, Vehicle $newVehicle, User $actor, string $reason, ?string $key = null): CaseVehicleAssignment
    {
        $newAssignment = DB::transaction(function () use ($assignment, $newVehicle, $actor, $reason, $key) {
            $a = CaseVehicleAssignment::lockForUpdate()->findOrFail($assignment->id);
            $case = EmergencyCase::lockForUpdate()->findOrFail($a->emergency_case_id);
            $vehicleIds = collect([$a->vehicle_id, $newVehicle->id])->sort()->values();
            $vehicles = Vehicle::whereIn('id', $vehicleIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldVehicle = $vehicles[$a->vehicle_id];
            $target = $vehicles[$newVehicle->id];
            if (! $a->status->isActive()) {
                throw new DispatchConflict('Mission is no longer active.');
            }
            if ($target->organization_id !== $case->organization_id || ! $target->is_active || $target->status !== VehicleStatus::Available) {
                throw new DispatchConflict('Unit '.$target->callsign.' is no longer available. Select another unit.');
            }
            $a->update(['status' => AssignmentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $oldVehicle->update(['status' => VehicleStatus::Available]);
            $created = CaseVehicleAssignment::create(['organization_id' => $case->organization_id, 'emergency_case_id' => $case->id, 'vehicle_id' => $target->id, 'status' => AssignmentStatus::Pending, 'idempotency_key' => $key, 'assigned_by' => $actor->id, 'assigned_at' => now()]);
            $target->update(['status' => VehicleStatus::Assigned]);
            $case->update(['status' => EmergencyCaseStatus::UnitAssigned, 'assigned_at' => now(), 'updated_by' => $actor->id, 'version' => $case->version + 1]);
            $this->timeline($case, $actor, 'UnitReassigned', 'Unit reassigned to '.$target->callsign, ['previous_assignment_id' => $a->id, 'assignment_id' => $created->id, 'reason' => $reason]);
            $this->audit->record('assignment.reassigned', $created, $case->organization, ['previous_assignment_id' => $a->id, 'reason' => $reason], actor: $actor);

            return $created;
        });
        $this->broadcast($newAssignment->emergencyCase, 'unit.reassigned', $newAssignment->vehicle_id, ['assignment_id' => $newAssignment->id]);

        return $newAssignment;
    }

    private function timeline(EmergencyCase $case, User $actor, string $type, string $summary, array $metadata = []): void
    {
        CaseEvent::create(['organization_id' => $case->organization_id, 'emergency_case_id' => $case->id, 'event_type' => $type, 'actor_type' => User::class, 'actor_id' => $actor->id, 'occurred_at' => now(), 'summary' => $summary, 'metadata' => $metadata, 'correlation_id' => $this->correlation->id()]);
    }

    private function broadcast(EmergencyCase $case, string $name, ?string $vehicleId = null, array $extra = []): void
    {
        try {
            event(new DispatchStateChanged($case->organization_id, $name, ['case_id' => $case->id, 'case_number' => $case->case_number, 'status' => $case->status->value, 'case_version' => $case->version, ...$extra], $this->correlation->id(), $case->id, $vehicleId));
        } catch (Throwable $e) {
            report($e);
            Log::warning('Realtime dispatch failed after persistence', ['case_id' => $case->id, 'correlation_id' => $this->correlation->id()]);
        }
    }
}
