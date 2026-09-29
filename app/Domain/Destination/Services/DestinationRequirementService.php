<?php

namespace App\Domain\Destination\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Destination\Enums\DestinationRequirementImportance;
use App\Domain\Destination\Enums\DestinationRequirementStatus;
use App\Domain\Destination\Enums\DestinationRequirementType;
use App\Domain\Destination\Exceptions\DestinationConflict;
use App\Events\DestinationStateChanged;
use App\Models\CaseEvent;
use App\Models\DestinationRequirement;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Support\CorrelationContext;
use Illuminate\Support\Facades\DB;

class DestinationRequirementService
{
    public function __construct(private AuditRecorder $audit, private CorrelationContext $correlation) {}

    public function create(PatientEncounter $encounter, User $actor, array $data): DestinationRequirement
    {
        return DB::transaction(function () use ($encounter, $actor, $data) {
            $locked = PatientEncounter::lockForUpdate()->with('emergencyCase.organization')->findOrFail($encounter->id);
            $this->assertTypeImportance($data['type'], $data['importance']);

            $requirement = DestinationRequirement::create([
                'organization_id' => $locked->organization_id,
                'emergency_case_id' => $locked->emergency_case_id,
                'patient_encounter_id' => $locked->id,
                'type' => $data['type'],
                'target_code' => strtoupper($data['target_code']),
                'importance' => $data['importance'],
                'source' => $data['source'] ?? 'MANUAL',
                'status' => DestinationRequirementStatus::Active,
                'notes' => $data['notes'] ?? null,
                'entered_by' => $actor->id,
                'confirmed_by' => $data['confirmed'] ?? false ? $actor->id : null,
            ]);
            $this->record($requirement, $locked, $actor, 'DestinationRequirementAdded', 'Destination requirement added');

            return $requirement;
        });
    }

    public function replace(DestinationRequirement $requirement, User $actor, array $data): DestinationRequirement
    {
        return DB::transaction(function () use ($requirement, $actor, $data) {
            $old = DestinationRequirement::lockForUpdate()->findOrFail($requirement->id);
            if ($old->status !== DestinationRequirementStatus::Active) {
                throw new DestinationConflict('Only an active destination requirement can be replaced.');
            }

            $encounter = PatientEncounter::lockForUpdate()->with('emergencyCase.organization')->findOrFail($old->patient_encounter_id);
            $this->assertTypeImportance($data['type'], $data['importance']);
            $old->update(['status' => DestinationRequirementStatus::Superseded]);

            $replacement = DestinationRequirement::create([
                'organization_id' => $old->organization_id,
                'emergency_case_id' => $old->emergency_case_id,
                'patient_encounter_id' => $old->patient_encounter_id,
                'type' => $data['type'],
                'target_code' => strtoupper($data['target_code']),
                'importance' => $data['importance'],
                'source' => $data['source'] ?? 'MANUAL',
                'status' => DestinationRequirementStatus::Active,
                'notes' => $data['notes'] ?? null,
                'entered_by' => $actor->id,
                'confirmed_by' => $data['confirmed'] ?? false ? $actor->id : null,
                'supersedes_requirement_id' => $old->id,
            ]);
            $this->record($replacement, $encounter, $actor, 'DestinationRequirementChanged', 'Destination requirement superseded');

            return $replacement;
        });
    }

    public function cancel(DestinationRequirement $requirement, User $actor, string $reason): DestinationRequirement
    {
        return DB::transaction(function () use ($requirement, $actor, $reason) {
            $locked = DestinationRequirement::lockForUpdate()->findOrFail($requirement->id);
            if ($locked->status !== DestinationRequirementStatus::Active) {
                throw new DestinationConflict('Only an active destination requirement can be cancelled.');
            }

            $encounter = PatientEncounter::lockForUpdate()->with('emergencyCase.organization')->findOrFail($locked->patient_encounter_id);
            $locked->update(['status' => DestinationRequirementStatus::Cancelled, 'notes' => trim($reason)]);
            $this->record($locked, $encounter, $actor, 'DestinationRequirementCancelled', 'Destination requirement cancelled');

            return $locked;
        });
    }

    private function assertTypeImportance(string $type, string $importance): void
    {
        $expected = $type === DestinationRequirementType::CapabilityPreferred->value
            ? DestinationRequirementImportance::Preferred->value
            : DestinationRequirementImportance::Required->value;

        if ($importance !== $expected) {
            throw new DestinationConflict('Requirement importance does not match its type.');
        }
    }

    private function record(DestinationRequirement $requirement, PatientEncounter $encounter, User $actor, string $eventType, string $summary): void
    {
        CaseEvent::create([
            'organization_id' => $encounter->organization_id,
            'emergency_case_id' => $encounter->emergency_case_id,
            'event_type' => $eventType,
            'event_version' => 1,
            'actor_type' => User::class,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
            'summary' => $summary,
            'metadata' => ['encounter_id' => $encounter->id, 'requirement_id' => $requirement->id, 'type' => $requirement->type->value, 'target_code' => $requirement->target_code, 'status' => $requirement->status->value],
            'correlation_id' => $this->correlation->id(),
        ]);
        $this->audit->record('destination.requirement.'.strtolower($requirement->status->value), $requirement, $encounter->emergencyCase->organization, ['case_id' => $encounter->emergency_case_id, 'encounter_id' => $encounter->id, 'type' => $requirement->type->value, 'target_code' => $requirement->target_code], actor: $actor);
        DB::afterCommit(fn () => DestinationStateChanged::dispatch($encounter->organization_id, $encounter->emergency_case_id, $encounter->id, 'destination.requirements.changed', ['requirement_id' => $requirement->id, 'status' => $requirement->status->value], $this->correlation->id()));
    }
}
