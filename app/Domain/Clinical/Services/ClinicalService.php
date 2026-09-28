<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Clinical\Enums\AssessmentStatus;
use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Enums\EncounterStatus;
use App\Domain\Clinical\Enums\ObservationSource;
use App\Domain\Clinical\Enums\PatientIdentityStatus;
use App\Domain\Clinical\Enums\VitalType;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Dispatch\Exceptions\DispatchConflict;
use App\Domain\Identity\Support\Permissions;
use App\Events\ClinicalStateChanged;
use App\Events\DispatchStateChanged;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\AssessmentTemplateVersion;
use App\Models\CaseEvent;
use App\Models\ClinicalNote;
use App\Models\EmergencyCase;
use App\Models\Patient;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VitalObservation;
use App\Support\CorrelationContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClinicalService
{
    public function __construct(private AmbulanceAccess $access, private AuditRecorder $audit, private CorrelationContext $correlation) {}

    public function transition(EmergencyCase $case, User $actor, EmergencyCaseStatus $target): EmergencyCase
    {
        $allowed = [
            EmergencyCaseStatus::UnitAccepted->value => EmergencyCaseStatus::EnRouteToScene,
            EmergencyCaseStatus::EnRouteToScene->value => EmergencyCaseStatus::OnScene,
            EmergencyCaseStatus::OnScene->value => EmergencyCaseStatus::PatientContact,
            EmergencyCaseStatus::PatientContact->value => EmergencyCaseStatus::Assessment,
            EmergencyCaseStatus::Assessment->value => EmergencyCaseStatus::DestinationPending,
        ];
        $assignment = $this->access->activeAssignment($case, $actor, Permissions::AmbulanceWorkflowUpdate);
        $case = DB::transaction(function () use ($case, $actor, $target, $allowed, $assignment) {
            $locked = EmergencyCase::lockForUpdate()->findOrFail($case->id);
            if (($allowed[$locked->status->value] ?? null) !== $target) {
                throw new DispatchConflict('Invalid ambulance workflow transition.');
            }
            if ($target === EmergencyCaseStatus::DestinationPending && ! $locked->encounters()->whereHas('assessments', fn ($query) => $query->where('status', AssessmentStatus::Completed->value))->exists()) {
                throw new DispatchConflict('A completed assessment is required before destination support readiness.');
            }
            $locked->update(['status' => $target, 'updated_by' => $actor->id, 'version' => $locked->version + 1]);
            if ($target === EmergencyCaseStatus::EnRouteToScene) {
                Vehicle::whereKey($assignment->vehicle_id)->update(['status' => VehicleStatus::EnRouteScene]);
            } elseif ($target === EmergencyCaseStatus::OnScene) {
                Vehicle::whereKey($assignment->vehicle_id)->update(['status' => VehicleStatus::OnScene]);
            }
            $summary = match ($target) {
                EmergencyCaseStatus::EnRouteToScene => 'Ambulance response started',
                EmergencyCaseStatus::OnScene => 'Ambulance arrived on scene',
                EmergencyCaseStatus::PatientContact => 'Patient contact established',
                EmergencyCaseStatus::Assessment => 'Clinical assessment workflow started',
                default => 'Case ready for destination support',
            };
            $this->timeline($locked, $actor, $target->value, $summary);
            $this->audit->record('ambulance.workflow.transitioned', $locked, $locked->organization, ['status' => $target->value], actor: $actor);

            return $locked;
        });
        $this->broadcastCase($case, 'workflow.transitioned');

        return $case;
    }

    public function createEncounter(EmergencyCase $case, User $actor, array $identity = []): PatientEncounter
    {
        $this->access->activeAssignment($case, $actor, Permissions::EncountersCreate);
        $encounter = DB::transaction(function () use ($case, $actor, $identity) {
            $locked = EmergencyCase::lockForUpdate()->findOrFail($case->id);
            if (! in_array($locked->status, [EmergencyCaseStatus::PatientContact, EmergencyCaseStatus::Assessment], true)) {
                throw new DispatchConflict('Patient contact must be established before creating an encounter.');
            }
            $patient = Patient::create([
                'organization_id' => $locked->organization_id,
                'identity_status' => $identity['identity_status'] ?? PatientIdentityStatus::Unidentified,
                'first_name' => $identity['first_name'] ?? null, 'last_name' => $identity['last_name'] ?? null,
                'estimated_age_min' => $identity['estimated_age_min'] ?? null, 'estimated_age_max' => $identity['estimated_age_max'] ?? null,
                'sex' => $identity['sex'] ?? null, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $index = ((int) PatientEncounter::where('emergency_case_id', $locked->id)->max('encounter_index')) + 1;
            $encounter = PatientEncounter::create([
                'organization_id' => $locked->organization_id, 'emergency_case_id' => $locked->id, 'patient_id' => $patient->id,
                'encounter_index' => $index, 'encounter_number' => $locked->case_number.'-P'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => EncounterStatus::Active, 'condition_level' => ConditionLevel::Unknown, 'contact_at' => now(),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->timeline($locked, $actor, 'PatientEncounterCreated', 'Patient encounter '.$encounter->encounter_number.' created', ['encounter_id' => $encounter->id]);
            $this->audit->record('encounter.created', $encounter, $locked->organization, ['case_id' => $locked->id], actor: $actor);

            return $encounter;
        });
        $this->broadcast($encounter, 'encounter.created', ['encounter_number' => $encounter->encounter_number]);

        return $encounter;
    }

    public function recordVital(PatientEncounter $encounter, User $actor, array $data): VitalObservation
    {
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::VitalsCreate);
        $operationId = $data['operation_id'] ?? null;

        if ($operationId && ($existing = $this->vitalForOperation($encounter, $operationId))) {
            return $existing;
        }

        try {
            $vital = DB::transaction(function () use ($encounter, $actor, $data) {
                $locked = PatientEncounter::lockForUpdate()->findOrFail($encounter->id);
                $type = VitalType::from($data['type']);
                $gcsTotal = $data['gcs_total'] ?? null;
                $derived = false;

                if ($type === VitalType::Gcs && $gcsTotal === null) {
                    $gcsTotal = (int) $data['gcs_eye'] + (int) $data['gcs_verbal'] + (int) $data['gcs_motor'];
                    $derived = true;
                }

                return VitalObservation::create([
                    ...$data,
                    'organization_id' => $locked->organization_id,
                    'patient_encounter_id' => $locked->id,
                    'type' => $type,
                    'gcs_total' => $gcsTotal,
                    'gcs_total_derived' => $derived,
                    'recorded_at' => now(),
                    'recorded_by' => $actor->id,
                    'source' => $data['source'] ?? ObservationSource::Manual,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $operationId || ! ($vital = $this->vitalForOperation($encounter, $operationId))) {
                throw $exception;
            }
        }

        $this->broadcast($encounter, 'vital.recorded', [
            'vital_id' => $vital->id,
            'type' => $vital->type->value,
            'measured_at' => $vital->measured_at->toIso8601String(),
        ]);

        return $vital;
    }

    public function correctVital(VitalObservation $original, User $actor, array $replacement, string $reason): VitalObservation
    {
        $encounter = $original->encounter;
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::VitalsCorrect);
        $operationId = $replacement['operation_id'] ?? null;

        if ($operationId && ($existing = $this->vitalForOperation($encounter, $operationId))) {
            if ($existing->supersedes_observation_id !== $original->id) {
                throw new DispatchConflict('Operation identifier is already used by another observation.');
            }

            return $existing;
        }

        try {
            $created = DB::transaction(function () use ($original, $actor, $replacement, $reason, $encounter) {
                $locked = VitalObservation::lockForUpdate()->findOrFail($original->id);
                if ($locked->superseded_at) {
                    throw new DispatchConflict('Observation has already been corrected.');
                }

                $type = VitalType::from($replacement['type']);
                $gcsTotal = $replacement['gcs_total'] ?? null;
                $derived = false;

                if ($type === VitalType::Gcs && $gcsTotal === null) {
                    $gcsTotal = (int) $replacement['gcs_eye'] + (int) $replacement['gcs_verbal'] + (int) $replacement['gcs_motor'];
                    $derived = true;
                }

                $created = VitalObservation::create([
                    ...$replacement,
                    'organization_id' => $encounter->organization_id,
                    'patient_encounter_id' => $encounter->id,
                    'type' => $type,
                    'gcs_total' => $gcsTotal,
                    'gcs_total_derived' => $derived,
                    'recorded_at' => now(),
                    'recorded_by' => $actor->id,
                    'source' => $replacement['source'] ?? ObservationSource::Manual,
                    'supersedes_observation_id' => $locked->id,
                    'correction_reason' => $reason,
                ]);
                $locked->update(['superseded_at' => now(), 'superseded_by' => $actor->id]);
                $this->audit->record('vital.corrected', $locked, $encounter->emergencyCase->organization, ['replacement_id' => $created->id], actor: $actor);

                return $created;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $operationId || ! ($created = $this->vitalForOperation($encounter, $operationId)) || $created->supersedes_observation_id !== $original->id) {
                throw $exception;
            }
        }

        $this->broadcast($encounter, 'vital.corrected', [
            'original_id' => $original->id,
            'replacement_id' => $created->id,
            'type' => $created->type->value,
        ]);

        return $created;
    }

    public function updatePatientIdentity(Patient $patient, PatientEncounter $encounter, User $actor, array $data, int $expectedVersion): Patient
    {
        abort_unless($encounter->patient_id === $patient->id && $patient->organization_id === $encounter->organization_id, 404);
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::PatientsUpdate);
        $updated = DB::transaction(function () use ($patient, $actor, $data, $expectedVersion, $encounter) {
            $locked = Patient::lockForUpdate()->findOrFail($patient->id);
            if ($locked->version !== $expectedVersion) {
                throw new DispatchConflict('Patient identity changed on the server. Reload before updating.');
            }
            $locked->update([...$data, 'version' => $locked->version + 1, 'updated_by' => $actor->id]);
            $this->audit->record('patient.identity.updated', $locked, $encounter->emergencyCase->organization, ['identity_status' => $locked->identity_status->value], actor: $actor);

            return $locked;
        });
        $this->broadcast($encounter, 'patient.identity.updated', ['identity_status' => $updated->identity_status->value, 'entity_version' => $updated->version]);

        return $updated;
    }

    public function startAssessment(PatientEncounter $encounter, AssessmentTemplateVersion $version, User $actor): Assessment
    {
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::AssessmentsCreate);
        abort_unless($version->template->organization_id === $encounter->organization_id, 404);

        $assessment = DB::transaction(function () use ($encounter, $version, $actor) {
            $locked = PatientEncounter::lockForUpdate()->findOrFail($encounter->id);
            $case = EmergencyCase::lockForUpdate()->findOrFail($locked->emergency_case_id);

            if ($case->status !== EmergencyCaseStatus::Assessment) {
                throw new DispatchConflict('The case must be in assessment before starting an assessment.');
            }

            $assessment = Assessment::create([
                'organization_id' => $locked->organization_id,
                'patient_encounter_id' => $locked->id,
                'assessment_template_version_id' => $version->id,
                'status' => AssessmentStatus::InProgress,
                'started_at' => now(),
                'created_by' => $actor->id,
            ]);
            $locked->update(['assessment_started_at' => $locked->assessment_started_at ?? now()]);
            $this->timeline($case, $actor, 'AssessmentStarted', 'Clinical assessment started', [
                'encounter_id' => $locked->id,
                'assessment_id' => $assessment->id,
                'template_version_id' => $version->id,
            ]);

            return $assessment;
        });

        $this->broadcast($encounter, 'assessment.started', [
            'assessment_id' => $assessment->id,
            'template_version_id' => $version->id,
        ]);

        return $assessment;
    }

    public function saveResponses(Assessment $assessment, User $actor, array $responses): Assessment
    {
        $this->access->activeAssignment($assessment->encounter->emergencyCase, $actor, Permissions::AssessmentsUpdate);

        return DB::transaction(function () use ($assessment, $actor, $responses) {
            $locked = Assessment::lockForUpdate()->findOrFail($assessment->id);

            if ($locked->status !== AssessmentStatus::InProgress) {
                throw new DispatchConflict('Completed assessments cannot be rewritten.');
            }

            $definition = collect($locked->templateVersion->definition['fields'] ?? [])->keyBy('key');
            if (collect(array_keys($responses))->diff($definition->keys())->isNotEmpty()) {
                throw new DispatchConflict('Assessment response does not match the template version.');
            }

            foreach ($responses as $key => $value) {
                AssessmentResponse::updateOrCreate(
                    ['assessment_id' => $locked->id, 'field_key' => $key],
                    ['value' => ['value' => $value], 'recorded_by' => $actor->id],
                );
            }

            return $locked->fresh('responses');
        });
    }

    public function completeAssessment(Assessment $assessment, User $actor): Assessment
    {
        $this->access->activeAssignment($assessment->encounter->emergencyCase, $actor, Permissions::AssessmentsComplete);
        $completed = DB::transaction(function () use ($assessment, $actor) {
            $locked = Assessment::lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== AssessmentStatus::InProgress) {
                throw new DispatchConflict('Assessment is not in progress.');
            }
            $required = collect($locked->templateVersion->definition['fields'] ?? [])->where('required', true)->pluck('key');
            $answered = $locked->responses()->pluck('field_key');
            if ($required->diff($answered)->isNotEmpty()) {
                throw new DispatchConflict('Complete all required assessment fields.');
            }
            $locked->update(['status' => AssessmentStatus::Completed, 'completed_at' => now(), 'completed_by' => $actor->id]);
            $locked->encounter->update(['assessment_completed_at' => now()]);
            $this->timeline($locked->encounter->emergencyCase, $actor, 'AssessmentCompleted', 'Clinical assessment completed', ['encounter_id' => $locked->patient_encounter_id, 'assessment_id' => $locked->id]);
            $this->audit->record('assessment.completed', $locked, $locked->encounter->emergencyCase->organization, actor: $actor);

            return $locked;
        });
        $this->broadcast($completed->encounter, 'assessment.completed', ['assessment_id' => $completed->id]);

        return $completed;
    }

    public function updateCondition(PatientEncounter $encounter, User $actor, ConditionLevel $level, int $expectedVersion): PatientEncounter
    {
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::EncountersUpdate);
        $updated = DB::transaction(function () use ($encounter, $actor, $level, $expectedVersion) {
            $locked = PatientEncounter::lockForUpdate()->findOrFail($encounter->id);
            if ($locked->version !== $expectedVersion) {
                throw new DispatchConflict('Encounter changed on the server. Reload before updating condition.');
            }$locked->update(['condition_level' => $level, 'version' => $locked->version + 1, 'updated_by' => $actor->id]);
            $this->timeline($locked->emergencyCase, $actor, 'PatientConditionChanged', 'Patient condition updated to '.$level->value, ['encounter_id' => $locked->id, 'condition' => $level->value]);
            $this->audit->record('encounter.condition.updated', $locked, $locked->emergencyCase->organization, ['condition' => $level->value], actor: $actor);

            return $locked;
        });
        $this->broadcast($updated, 'condition.changed', ['condition' => $level->value, 'entity_version' => $updated->version]);

        return $updated;
    }

    public function addNote(PatientEncounter $encounter, User $actor, string $body, ?string $operationId = null): ClinicalNote
    {
        $this->access->activeAssignment($encounter->emergencyCase, $actor, Permissions::ClinicalNotesCreate);

        if ($operationId && ($existing = ClinicalNote::where('organization_id', $encounter->organization_id)->where('operation_id', $operationId)->first())) {
            if ($existing->patient_encounter_id !== $encounter->id) {
                throw new DispatchConflict('Operation identifier is already used by another clinical note.');
            }

            return $existing;
        }

        return DB::transaction(function () use ($encounter, $actor, $body, $operationId) {
            $note = ClinicalNote::create([
                'organization_id' => $encounter->organization_id,
                'patient_encounter_id' => $encounter->id,
                'body' => $body,
                'recorded_at' => now(),
                'recorded_by' => $actor->id,
                'operation_id' => $operationId,
            ]);
            $this->audit->record('clinical_note.created', $note, $encounter->emergencyCase->organization, actor: $actor);

            return $note;
        });
    }

    private function vitalForOperation(PatientEncounter $encounter, string $operationId): ?VitalObservation
    {
        $existing = VitalObservation::query()
            ->where('organization_id', $encounter->organization_id)
            ->where('operation_id', $operationId)
            ->first();

        if ($existing && $existing->patient_encounter_id !== $encounter->id) {
            throw new DispatchConflict('Operation identifier is already used by another observation.');
        }

        return $existing;
    }

    private function timeline(EmergencyCase $case, User $actor, string $type, string $summary, array $metadata = []): void
    {
        CaseEvent::create(['organization_id' => $case->organization_id, 'emergency_case_id' => $case->id, 'event_type' => $type, 'actor_type' => User::class, 'actor_id' => $actor->id, 'occurred_at' => now(), 'summary' => $summary, 'metadata' => $metadata, 'correlation_id' => $this->correlation->id()]);
    }

    private function broadcastCase(EmergencyCase $case, string $name): void
    {
        try {
            $assignment = $case->assignments()->active()->latest('assigned_at')->first();
            event(new DispatchStateChanged(
                $case->organization_id,
                $name,
                ['case_id' => $case->id, 'status' => $case->status->value, 'case_version' => $case->version],
                $this->correlation->id(),
                $case->id,
                $assignment?->vehicle_id,
            ));
        } catch (Throwable $e) {
            report($e);
            Log::warning('Ambulance workflow realtime failed after persistence', ['case_id' => $case->id, 'correlation_id' => $this->correlation->id()]);
        }
    }

    private function broadcast(PatientEncounter $encounter, string $name, array $data): void
    {
        try {
            event(new ClinicalStateChanged($encounter->organization_id, $encounter->emergency_case_id, $encounter->id, $name, $data, $this->correlation->id()));
        } catch (Throwable $e) {
            report($e);
            Log::warning('Clinical realtime failed after persistence', ['encounter_id' => $encounter->id, 'correlation_id' => $this->correlation->id()]);
        }
    }
}
