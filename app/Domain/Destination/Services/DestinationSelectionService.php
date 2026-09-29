<?php

namespace App\Domain\Destination\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Destination\Enums\DestinationCandidateOutcome;
use App\Domain\Destination\Enums\DestinationSelectionType;
use App\Domain\Destination\Exceptions\DestinationConflict;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Hospitals\Services\HospitalOperationsService;
use App\Domain\Identity\Support\Permissions;
use App\Events\DestinationStateChanged;
use App\Models\CaseEvent;
use App\Models\DestinationCandidateEvaluation;
use App\Models\DestinationSelection;
use App\Models\EmergencyCase;
use App\Models\Hospital;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Support\CorrelationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DestinationSelectionService
{
    public function __construct(
        private DestinationAccess $access,
        private HospitalOperationsService $hospitals,
        private AuditRecorder $audit,
        private CorrelationContext $correlation,
    ) {}

    public function select(PatientEncounter $encounter, Hospital $hospital, User $actor, array $data): DestinationSelection
    {
        $this->access->ensureEncounter($actor, $encounter, Permissions::DestinationSelectionSelect);
        $this->access->ensureHospital($actor, $hospital);

        $existing = DestinationSelection::query()
            ->where('organization_id', $encounter->organization_id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();
        if ($existing) {
            if ($existing->patient_encounter_id !== $encounter->id || $existing->hospital_id !== $hospital->id) {
                throw new DestinationConflict('Selection idempotency key is already used for another decision.');
            }

            return $existing;
        }

        $selection = DB::transaction(function () use ($encounter, $hospital, $actor, $data) {
            $case = EmergencyCase::lockForUpdate()->with('organization')->findOrFail($encounter->emergency_case_id);
            $lockedEncounter = PatientEncounter::lockForUpdate()->findOrFail($encounter->id);
            if ($lockedEncounter->organization_id !== $case->organization_id || $lockedEncounter->destination_version !== (int) $data['expected_destination_version']) {
                Log::notice('Destination selection version conflict', ['encounter_id' => $lockedEncounter->id, 'expected_version' => $data['expected_destination_version'], 'current_version' => $lockedEncounter->destination_version, 'correlation_id' => $this->correlation->id()]);
                throw new DestinationConflict('Destination state changed on the server. Reload before selecting.');
            }

            $current = DestinationSelection::query()
                ->where('patient_encounter_id', $lockedEncounter->id)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->first();

            if ($current && ! $actor->hasPermission(Permissions::DestinationSelectionChange, $case->organization)) {
                abort(403);
            }
            if ($current && blank($data['reason'] ?? null)) {
                throw new DestinationConflict('A reason is required when changing destination.');
            }

            $candidate = null;
            $type = DestinationSelectionType::ManualWithoutEvaluation;
            if (! empty($data['candidate_id'])) {
                $candidate = DestinationCandidateEvaluation::query()->with('evaluation')->lockForUpdate()->findOrFail($data['candidate_id']);
                if ($candidate->evaluation->organization_id !== $case->organization_id || $candidate->evaluation->patient_encounter_id !== $lockedEncounter->id || $candidate->hospital_id !== $hospital->id) {
                    throw new DestinationConflict('Candidate does not belong to this encounter and hospital.');
                }

                $type = match ($candidate->outcome) {
                    DestinationCandidateOutcome::Eligible => DestinationSelectionType::EligibleSelection,
                    DestinationCandidateOutcome::Unknown => DestinationSelectionType::UnknownOverride,
                    DestinationCandidateOutcome::Ineligible => DestinationSelectionType::IneligibleOverride,
                };
            }

            $override = $type !== DestinationSelectionType::EligibleSelection;
            if ($override && ! $actor->hasPermission(Permissions::DestinationSelectionOverride, $case->organization)) {
                abort(403);
            }
            if ($override && blank($data['reason'] ?? null)) {
                throw new DestinationConflict('An explicit reason is required for manual or override selection.');
            }

            if ($current) {
                $current->update(['superseded_at' => now(), 'superseded_by' => $actor->id]);
            }

            $selection = DestinationSelection::create([
                'organization_id' => $case->organization_id,
                'emergency_case_id' => $case->id,
                'patient_encounter_id' => $lockedEncounter->id,
                'destination_evaluation_id' => $candidate?->destination_evaluation_id,
                'destination_candidate_evaluation_id' => $candidate?->id,
                'hospital_id' => $hospital->id,
                'selection_type' => $type,
                'reason' => $data['reason'] ?? null,
                'selected_by' => $actor->id,
                'supersedes_selection_id' => $current?->id,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $lockedEncounter->increment('destination_version');

            if ($case->status === EmergencyCaseStatus::DestinationPending) {
                $case->update(['status' => EmergencyCaseStatus::DestinationSelected, 'version' => $case->version + 1, 'updated_by' => $actor->id]);
            } elseif ($case->status !== EmergencyCaseStatus::DestinationSelected) {
                throw new DestinationConflict('Case is not ready for destination selection.');
            }

            CaseEvent::create([
                'organization_id' => $case->organization_id,
                'emergency_case_id' => $case->id,
                'event_type' => $current ? 'DestinationChanged' : 'DestinationSelected',
                'event_version' => 1,
                'actor_type' => User::class,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
                'summary' => $current ? 'Patient destination changed' : 'Patient destination selected',
                'metadata' => ['encounter_id' => $lockedEncounter->id, 'selection_id' => $selection->id, 'hospital_id' => $hospital->id, 'selection_type' => $type->value, 'supersedes_selection_id' => $current?->id],
                'correlation_id' => $this->correlation->id(),
            ]);
            $this->audit->record('destination.selection.created', $selection, $case->organization, ['case_id' => $case->id, 'encounter_id' => $lockedEncounter->id, 'hospital_id' => $hospital->id, 'selection_type' => $type->value, 'changed' => (bool) $current], actor: $actor);

            $this->hospitals->notify($hospital, $case, $actor, $lockedEncounter, 'destination-selection-'.$selection->id);

            return $selection;
        }, 3);

        $fresh = $selection->fresh(['hospital', 'candidate', 'evaluation']);
        DestinationStateChanged::dispatch($fresh->organization_id, $fresh->emergency_case_id, $fresh->patient_encounter_id, 'destination.selection.changed', ['selection_id' => $fresh->id, 'hospital_id' => $fresh->hospital_id, 'selection_type' => $fresh->selection_type->value], $this->correlation->id());

        return $fresh;
    }
}
