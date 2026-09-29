<?php

namespace App\Domain\Hospitals\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Hospitals\Enums\NotificationStatus;
use App\Domain\Hospitals\Enums\RestrictionStatus;
use App\Domain\Hospitals\Exceptions\HospitalStateConflict;
use App\Events\HospitalStateChanged;
use App\Models\CaseEvent;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCapabilityAvailability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalOperationalRestriction;
use App\Models\HospitalReceivingStatus;
use App\Models\HospitalResourceDefinition;
use App\Models\HospitalResourceState;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Support\CorrelationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HospitalOperationsService
{
    public function __construct(private AuditRecorder $audit, private CorrelationContext $correlation) {}

    public function updateReceiving(Hospital $hospital, User $actor, array $data): HospitalReceivingStatus
    {
        if ($existing = $this->idempotent(HospitalReceivingStatus::class, $hospital, $data['idempotency_key'])) {
            return $existing;
        }

        [$report, $version] = DB::transaction(function () use ($hospital, $actor, $data) {
            $locked = $this->lockVersion($hospital, (int) $data['expected_version']);
            $report = HospitalReceivingStatus::create($this->reportData($locked, $actor, $data));
            $locked->increment('version');
            $this->audit->record('hospital.receiving.updated', $report, $locked->organization, ['hospital_id' => $locked->id, 'status' => $report->status->value], actor: $actor);

            return [$report, $locked->version];
        });

        $this->broadcast($hospital, 'hospital.receiving.changed', ['receiving_status_id' => $report->id, 'status' => $report->status->value, 'hospital_version' => $version]);

        return $report;
    }

    public function updateCapabilityAvailability(HospitalCapability $capability, User $actor, array $data): HospitalCapabilityAvailability
    {
        $hospital = $capability->hospital;
        if ($existing = $this->idempotent(HospitalCapabilityAvailability::class, $hospital, $data['idempotency_key'])) {
            if ($existing->hospital_capability_id !== $capability->id) {
                throw new HospitalStateConflict('Idempotency key is already used for another capability.');
            }

            return $existing;
        }

        [$report, $version] = DB::transaction(function () use ($hospital, $capability, $actor, $data) {
            $locked = $this->lockVersion($hospital, (int) $data['expected_version']);
            $lockedCapability = HospitalCapability::lockForUpdate()->findOrFail($capability->id);
            if ($lockedCapability->hospital_id !== $locked->id || ! $lockedCapability->enabled) {
                throw new HospitalStateConflict('Capability is not enabled for this hospital.');
            }
            $report = HospitalCapabilityAvailability::create([
                ...$this->reportData($locked, $actor, $data),
                'hospital_capability_id' => $lockedCapability->id,
            ]);
            $locked->increment('version');
            $this->audit->record('hospital.capability_availability.updated', $report, $locked->organization, ['hospital_id' => $locked->id, 'capability_id' => $lockedCapability->id, 'status' => $report->status->value], actor: $actor);

            return [$report, $locked->version];
        });

        $this->broadcast($hospital, 'hospital.capability.availability.changed', ['capability_id' => $capability->id, 'availability_id' => $report->id, 'status' => $report->status->value, 'hospital_version' => $version]);

        return $report;
    }

    public function updateResource(Hospital $hospital, HospitalResourceDefinition $definition, User $actor, array $data): HospitalResourceState
    {
        if ($existing = $this->idempotent(HospitalResourceState::class, $hospital, $data['idempotency_key'])) {
            if ($existing->hospital_resource_definition_id !== $definition->id) {
                throw new HospitalStateConflict('Idempotency key is already used for another resource.');
            }

            return $existing;
        }

        [$report, $version] = DB::transaction(function () use ($hospital, $definition, $actor, $data) {
            $locked = $this->lockVersion($hospital, (int) $data['expected_version']);
            $report = HospitalResourceState::create([
                'organization_id' => $locked->organization_id,
                'hospital_id' => $locked->id,
                'hospital_resource_definition_id' => $definition->id,
                'total_capacity' => $data['total_capacity'] ?? null,
                'available_capacity' => $data['available_capacity'] ?? null,
                'status' => $data['status'],
                'effective_at' => $data['effective_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'reported_by' => $actor->id,
                'source' => $data['source'],
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $locked->increment('version');
            $this->audit->record('hospital.resource.updated', $report, $locked->organization, ['hospital_id' => $locked->id, 'resource_definition_id' => $definition->id, 'status' => $report->status->value], actor: $actor);

            return [$report, $locked->version];
        });

        $this->broadcast($hospital, 'hospital.resource.changed', ['resource_definition_id' => $definition->id, 'resource_state_id' => $report->id, 'status' => $report->status->value, 'hospital_version' => $version]);

        return $report;
    }

    public function createRestriction(Hospital $hospital, User $actor, array $data): HospitalOperationalRestriction
    {
        if ($existing = $this->idempotent(HospitalOperationalRestriction::class, $hospital, $data['idempotency_key'])) {
            return $existing;
        }

        [$restriction, $version] = DB::transaction(function () use ($hospital, $actor, $data) {
            $locked = $this->lockVersion($hospital, (int) $data['expected_version']);
            $restriction = HospitalOperationalRestriction::create([
                'organization_id' => $locked->organization_id,
                'hospital_id' => $locked->id,
                'status' => RestrictionStatus::Active,
                'reason_code' => $data['reason_code'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'effective_at' => $data['effective_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'reported_by' => $actor->id,
                'source' => $data['source'],
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $locked->increment('version');
            $this->audit->record('hospital.restriction.created', $restriction, $locked->organization, ['hospital_id' => $locked->id, 'reason_code' => $restriction->reason_code->value], actor: $actor);

            return [$restriction, $locked->version];
        });

        $this->broadcast($hospital, 'hospital.restriction.changed', ['restriction_id' => $restriction->id, 'status' => $restriction->status->value, 'hospital_version' => $version]);

        return $restriction;
    }

    public function notify(Hospital $hospital, EmergencyCase $case, User $actor, ?PatientEncounter $encounter, string $idempotencyKey): HospitalCaseNotification
    {
        $sharedForDestination = $case->organization_id !== $hospital->organization_id
            && DB::table('destination_hospital_access')
                ->where('organization_id', $case->organization_id)
                ->where('hospital_id', $hospital->id)
                ->where('active', true)
                ->exists();

        if ($case->organization_id !== $hospital->organization_id && ! $sharedForDestination) {
            throw new HospitalStateConflict('Case and hospital are not connected by an active destination network agreement.');
        }

        $existing = HospitalCaseNotification::where('organization_id', $hospital->organization_id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->hospital_id !== $hospital->id || $existing->emergency_case_id !== $case->id) {
                throw new HospitalStateConflict('Idempotency key is already used for another hospital notification.');
            }

            return $existing;
        }

        $notification = DB::transaction(function () use ($hospital, $case, $actor, $encounter, $idempotencyKey) {
            $lockedHospital = Hospital::lockForUpdate()->findOrFail($hospital->id);
            $lockedCase = EmergencyCase::lockForUpdate()->findOrFail($case->id);
            if ($encounter && $encounter->emergency_case_id !== $lockedCase->id) {
                throw new HospitalStateConflict('Encounter does not belong to the notified case.');
            }
            $notification = HospitalCaseNotification::create([
                'organization_id' => $lockedHospital->organization_id,
                'hospital_id' => $lockedHospital->id,
                'emergency_case_id' => $lockedCase->id,
                'patient_encounter_id' => $encounter?->id,
                'status' => NotificationStatus::Pending,
                'notified_at' => now(),
                'created_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->timeline($lockedCase, $actor, 'HospitalNotified', 'Hospital notified about incoming case', ['hospital_id' => $lockedHospital->id, 'notification_id' => $notification->id]);
            $this->audit->record('hospital.notification.created', $notification, $lockedHospital->organization, ['hospital_id' => $lockedHospital->id, 'case_id' => $lockedCase->id], actor: $actor);

            return $notification;
        });

        DB::afterCommit(fn () => $this->broadcast($hospital, 'hospital.incoming.created', ['notification_id' => $notification->id, 'case_id' => $case->id, 'case_number' => $case->case_number, 'priority' => $case->priority->value, 'status' => $notification->status->value], $case->id));

        return $notification;
    }

    public function markDelivered(HospitalCaseNotification $notification, User $actor): HospitalCaseNotification
    {
        if (in_array($notification->status, [NotificationStatus::Delivered, NotificationStatus::Acknowledged], true)) {
            return $notification;
        }

        $notification = DB::transaction(function () use ($notification, $actor) {
            $locked = HospitalCaseNotification::lockForUpdate()->findOrFail($notification->id);
            if ($locked->status !== NotificationStatus::Pending) {
                throw new HospitalStateConflict('Notification cannot be marked delivered in its current state.');
            }
            $locked->update(['status' => NotificationStatus::Delivered, 'delivered_at' => now()]);
            $this->audit->record('hospital.notification.delivered', $locked, $locked->hospital->organization, ['hospital_id' => $locked->hospital_id, 'case_id' => $locked->emergency_case_id], actor: $actor);

            return $locked;
        });

        $this->broadcast($notification->hospital, 'hospital.incoming.delivered', ['notification_id' => $notification->id, 'case_id' => $notification->emergency_case_id, 'status' => $notification->status->value], $notification->emergency_case_id);

        return $notification;
    }

    public function acknowledge(HospitalCaseNotification $notification, User $actor): HospitalCaseNotification
    {
        if ($notification->status === NotificationStatus::Acknowledged) {
            return $notification;
        }

        $notification = DB::transaction(function () use ($notification, $actor) {
            $locked = HospitalCaseNotification::lockForUpdate()->findOrFail($notification->id);
            if (! in_array($locked->status, [NotificationStatus::Pending, NotificationStatus::Delivered], true)) {
                throw new HospitalStateConflict('Notification cannot be acknowledged in its current state.');
            }
            $locked->update(['status' => NotificationStatus::Acknowledged, 'delivered_at' => $locked->delivered_at ?? now(), 'acknowledged_at' => now(), 'acknowledged_by' => $actor->id]);
            $this->timeline($locked->emergencyCase, $actor, 'HospitalNotificationAcknowledged', 'Hospital operator acknowledged the incoming notification', ['hospital_id' => $locked->hospital_id, 'notification_id' => $locked->id]);
            $this->audit->record('hospital.notification.acknowledged', $locked, $locked->hospital->organization, ['hospital_id' => $locked->hospital_id, 'case_id' => $locked->emergency_case_id], actor: $actor);

            return $locked;
        });

        $this->broadcast($notification->hospital, 'hospital.incoming.acknowledged', ['notification_id' => $notification->id, 'case_id' => $notification->emergency_case_id, 'status' => $notification->status->value], $notification->emergency_case_id);

        return $notification;
    }

    private function lockVersion(Hospital $hospital, int $expectedVersion): Hospital
    {
        $locked = Hospital::lockForUpdate()->findOrFail($hospital->id);
        if ($locked->version !== $expectedVersion) {
            Log::notice('Hospital operational version conflict', ['hospital_id' => $locked->id, 'expected_version' => $expectedVersion, 'current_version' => $locked->version, 'correlation_id' => $this->correlation->id()]);
            throw new HospitalStateConflict('Hospital state changed on the server. Reload before updating.');
        }

        return $locked;
    }

    private function reportData(Hospital $hospital, User $actor, array $data): array
    {
        return ['organization_id' => $hospital->organization_id, 'hospital_id' => $hospital->id, 'status' => $data['status'], 'reason_code' => $data['reason_code'] ?? null, 'reason_text' => $data['reason_text'] ?? null, 'effective_at' => $data['effective_at'], 'expires_at' => $data['expires_at'] ?? null, 'reported_by' => $actor->id, 'source' => $data['source'], 'idempotency_key' => $data['idempotency_key']];
    }

    private function idempotent(string $model, Hospital $hospital, string $key): ?Model
    {
        $existing = $model::where('organization_id', $hospital->organization_id)->where('idempotency_key', $key)->first();
        if ($existing && $existing->hospital_id !== $hospital->id) {
            throw new HospitalStateConflict('Idempotency key is already used for another hospital.');
        }

        return $existing;
    }

    private function timeline(EmergencyCase $case, User $actor, string $type, string $summary, array $metadata): void
    {
        CaseEvent::create(['organization_id' => $case->organization_id, 'emergency_case_id' => $case->id, 'event_type' => $type, 'actor_type' => User::class, 'actor_id' => $actor->id, 'occurred_at' => now(), 'summary' => $summary, 'metadata' => $metadata, 'correlation_id' => $this->correlation->id()]);
    }

    private function broadcast(Hospital $hospital, string $event, array $data, ?string $caseId = null): void
    {
        try {
            event(new HospitalStateChanged($hospital->id, $hospital->organization_id, $event, $data, $this->correlation->id(), $caseId));
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Hospital realtime failed after persistence', ['hospital_id' => $hospital->id, 'event' => $event, 'correlation_id' => $this->correlation->id()]);
        }
    }
}
