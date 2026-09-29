<?php

namespace App\Domain\Destination\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Destination\Enums\DestinationCandidateOutcome;
use App\Domain\Destination\Enums\DestinationEvaluationStatus;
use App\Domain\Destination\Enums\DestinationRequirementImportance;
use App\Domain\Destination\Enums\DestinationRequirementStatus;
use App\Domain\Destination\Enums\DestinationRequirementType;
use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use App\Domain\Destination\Exceptions\DestinationConflict;
use App\Domain\Hospitals\Services\HospitalOperationalSnapshotService;
use App\Domain\Identity\Support\Permissions;
use App\Events\DestinationStateChanged;
use App\Models\CaseEvent;
use App\Models\DestinationCandidateEvaluation;
use App\Models\DestinationEvaluation;
use App\Models\DestinationRequirementSet;
use App\Models\DestinationRuleSetVersion;
use App\Models\PatientEncounter;
use App\Models\User;
use App\Support\CorrelationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DestinationEvaluationService
{
    public function __construct(
        private DestinationAccess $access,
        private DestinationRuleDefinitionValidator $validator,
        private HospitalOperationalSnapshotService $snapshots,
        private AuditRecorder $audit,
        private CorrelationContext $correlation,
    ) {}

    public function evaluate(PatientEncounter $encounter, DestinationRuleSetVersion $version, User $actor, string $idempotencyKey): DestinationEvaluation
    {
        $this->access->ensureEncounter($actor, $encounter, Permissions::DestinationEvaluationsCreate);

        $existing = DestinationEvaluation::query()
            ->where('organization_id', $encounter->organization_id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if ($existing->patient_encounter_id !== $encounter->id) {
                throw new DestinationConflict('Evaluation idempotency key is already used for another encounter.');
            }

            return $existing->load('candidates.hospital', 'requirementSet.requirements', 'ruleVersion.ruleSet');
        }

        $referenceTime = CarbonImmutable::now('UTC');
        $placeholderSet = DB::transaction(function () use ($encounter, $actor, $referenceTime) {
            $locked = PatientEncounter::lockForUpdate()->findOrFail($encounter->id);

            return DestinationRequirementSet::create([
                'organization_id' => $locked->organization_id,
                'emergency_case_id' => $locked->emergency_case_id,
                'patient_encounter_id' => $locked->id,
                'created_by' => $actor->id,
                'captured_at' => $referenceTime,
            ]);
        });

        $evaluation = DestinationEvaluation::create([
            'organization_id' => $encounter->organization_id,
            'emergency_case_id' => $encounter->emergency_case_id,
            'patient_encounter_id' => $encounter->id,
            'destination_requirement_set_id' => $placeholderSet->id,
            'destination_rule_set_version_id' => $version->id,
            'status' => DestinationEvaluationStatus::Pending,
            'reference_time' => $referenceTime,
            'requested_by' => $actor->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $setRepeatableRead = DB::transactionLevel() === 0;

        try {
            DB::transaction(function () use ($evaluation, $version, $actor, $referenceTime, $setRepeatableRead) {
                if ($setRepeatableRead) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                }
                $lockedEvaluation = DestinationEvaluation::lockForUpdate()->findOrFail($evaluation->id);
                $lockedEncounter = PatientEncounter::lockForUpdate()->with('emergencyCase.organization')->findOrFail($lockedEvaluation->patient_encounter_id);
                $lockedVersion = DestinationRuleSetVersion::lockForUpdate()->with('ruleSet')->findOrFail($version->id);

                if ($lockedVersion->organization_id !== $lockedEncounter->organization_id || $lockedVersion->status !== DestinationRuleVersionStatus::Active) {
                    throw new DestinationConflict('Only an active rule version owned by the encounter organization may be used.');
                }

                $definition = $this->validator->validate($lockedVersion->definition);
                $requirements = $lockedEncounter->destinationRequirements()
                    ->where('status', DestinationRequirementStatus::Active->value)
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                if ($requirements->isEmpty()) {
                    throw new DestinationConflict('At least one active destination requirement is required.');
                }

                foreach ($requirements as $requirement) {
                    DB::table('destination_requirement_set_items')->insert([
                        'id' => (string) Str::ulid(),
                        'destination_requirement_set_id' => $lockedEvaluation->destination_requirement_set_id,
                        'destination_requirement_id' => $requirement->id,
                        'created_at' => $referenceTime,
                    ]);
                }

                $lockedEvaluation->update(['status' => DestinationEvaluationStatus::Running, 'started_at' => now()]);
                $hospitals = $this->access->hospitalQuery($actor)
                    ->with(['capabilities.definition', 'capabilities.department', 'capabilities.availabilities', 'receivingStatuses', 'resourceStates.definition', 'restrictions'])
                    ->orderBy('name')
                    ->get();

                if ($hospitals->isEmpty()) {
                    throw new DestinationConflict('No explicitly shared hospitals are available for evaluation.');
                }

                foreach ($hospitals as $hospital) {
                    $snapshot = $this->snapshots->for($hospital, $referenceTime);
                    [$outcome, $evidence, $summary] = $this->evaluateCandidate($requirements->all(), $snapshot, $definition, $lockedVersion);
                    DestinationCandidateEvaluation::create([
                        'destination_evaluation_id' => $lockedEvaluation->id,
                        'hospital_id' => $hospital->id,
                        'outcome' => $outcome,
                        'explanation_summary' => $summary,
                        'snapshot_data' => $snapshot,
                        'evidence_data' => ['checks' => $evidence],
                    ]);
                }

                $lockedEvaluation->update(['status' => DestinationEvaluationStatus::Completed, 'completed_at' => now()]);
                CaseEvent::create([
                    'organization_id' => $lockedEncounter->organization_id,
                    'emergency_case_id' => $lockedEncounter->emergency_case_id,
                    'event_type' => 'DestinationEvaluationCompleted',
                    'event_version' => 1,
                    'actor_type' => User::class,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                    'summary' => 'Destination compatibility evaluation completed',
                    'metadata' => ['encounter_id' => $lockedEncounter->id, 'evaluation_id' => $lockedEvaluation->id, 'rule_version_id' => $lockedVersion->id, 'candidate_count' => $hospitals->count()],
                    'correlation_id' => $this->correlation->id(),
                ]);
                $this->audit->record('destination.evaluation.completed', $lockedEvaluation, $lockedEncounter->emergencyCase->organization, ['case_id' => $lockedEncounter->emergency_case_id, 'encounter_id' => $lockedEncounter->id, 'rule_version_id' => $lockedVersion->id, 'candidate_count' => $hospitals->count()], actor: $actor);
            }, 3);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($evaluation, $exception) {
                DestinationCandidateEvaluation::where('destination_evaluation_id', $evaluation->id)->delete();
                DestinationEvaluation::whereKey($evaluation->id)->update([
                    'status' => DestinationEvaluationStatus::Failed->value,
                    'completed_at' => now(),
                    'failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
            });
            Log::error('Destination evaluation failed', ['evaluation_id' => $evaluation->id, 'encounter_id' => $encounter->id, 'correlation_id' => $this->correlation->id(), 'exception' => $exception::class]);
            throw $exception;
        }

        $completed = $evaluation->fresh()->load('candidates.hospital', 'requirementSet.requirements', 'ruleVersion.ruleSet');
        DestinationStateChanged::dispatch($completed->organization_id, $completed->emergency_case_id, $completed->patient_encounter_id, 'destination.evaluation.completed', ['evaluation_id' => $completed->id, 'status' => $completed->status->value], $this->correlation->id());

        return $completed;
    }

    private function evaluateCandidate(array $requirements, array $snapshot, array $definition, DestinationRuleSetVersion $version): array
    {
        $checks = [];
        foreach ($requirements as $requirement) {
            $hard = $requirement->importance === DestinationRequirementImportance::Required;
            $check = match ($requirement->type) {
                DestinationRequirementType::ReceivingRequired => $this->receivingCheck($snapshot, $definition),
                DestinationRequirementType::CapabilityRequired,
                DestinationRequirementType::CapabilityPreferred => $this->capabilityCheck($requirement->target_code, $snapshot, $definition),
                DestinationRequirementType::ResourceRequired => $this->resourceCheck($requirement->target_code, $snapshot, $definition),
            };
            $checks[] = array_merge($check, [
                'requirement_id' => $requirement->id,
                'requirement_type' => $requirement->type->value,
                'target_code' => $requirement->target_code,
                'hard_requirement' => $hard,
                'rule_version' => $version->version,
            ]);
        }

        if (collect($requirements)->contains(fn ($requirement) => $requirement->importance === DestinationRequirementImportance::Required) && count($snapshot['restrictions']) > 0) {
            $checks[] = [
                'rule_id' => 'DS-RESTRICTION-001',
                'result' => $definition['restrictions']['active_result'],
                'observed' => collect($snapshot['restrictions'])->pluck('reason_code')->all(),
                'reason' => 'Active operational restriction present.',
                'hard_requirement' => true,
                'rule_version' => $version->version,
            ];
        }

        $hardResults = collect($checks)->where('hard_requirement', true)->pluck('result');
        $outcome = $hardResults->contains('FAIL')
            ? DestinationCandidateOutcome::Ineligible
            : ($hardResults->contains('UNKNOWN') ? DestinationCandidateOutcome::Unknown : DestinationCandidateOutcome::Eligible);

        $counts = collect($checks)->countBy('result');
        $summary = sprintf('%s: %d pass, %d fail, %d unknown.', $outcome->value, $counts->get('PASS', 0), $counts->get('FAIL', 0), $counts->get('UNKNOWN', 0));

        return [$outcome, $checks, $summary];
    }

    private function receivingCheck(array $snapshot, array $definition): array
    {
        $receiving = $snapshot['receiving'];
        $result = match ($receiving['value']) {
            'OPEN' => 'PASS',
            'NOT_RECEIVING' => 'FAIL',
            'LIMITED' => $definition['receiving']['limited_result'],
            default => 'UNKNOWN',
        };

        if (in_array($receiving['freshness'], ['STALE', 'UNKNOWN'], true) && $result === 'PASS') {
            $result = $definition['freshness']['stale_result'];
        }

        return ['rule_id' => 'DS-RECEIVING-001', 'result' => $result, 'observed' => $receiving, 'reason' => 'Hospital receiving state and freshness evaluated.'];
    }

    private function capabilityCheck(string $code, array $snapshot, array $definition): array
    {
        $capability = collect($snapshot['capabilities'])->firstWhere('code', $code);
        if (! $capability || ! $capability['enabled']) {
            return ['rule_id' => 'DS-CAPABILITY-001', 'result' => $definition['capability']['missing_result'], 'observed' => $capability, 'reason' => 'Required capability is missing or disabled.'];
        }

        $availability = $capability['availability'];
        $result = match ($availability['value']) {
            'AVAILABLE' => 'PASS',
            'LIMITED' => $definition['capability']['limited_result'],
            'UNAVAILABLE' => 'FAIL',
            default => 'UNKNOWN',
        };
        if (in_array($availability['freshness'], ['STALE', 'UNKNOWN'], true) && $result === 'PASS') {
            $result = $definition['freshness']['stale_result'];
        }

        return ['rule_id' => 'DS-CAPABILITY-001', 'result' => $result, 'observed' => $capability, 'reason' => 'Capability existence, availability and freshness evaluated.'];
    }

    private function resourceCheck(string $code, array $snapshot, array $definition): array
    {
        $resource = collect($snapshot['resources'])->firstWhere('code', $code);
        if (! $resource) {
            return ['rule_id' => 'DS-RESOURCE-001', 'result' => $definition['resource']['missing_result'], 'observed' => null, 'reason' => 'Required resource state is missing.'];
        }

        $result = match ($resource['status']) {
            'AVAILABLE' => 'PASS',
            'LIMITED' => $definition['resource']['limited_result'],
            'FULL', 'UNAVAILABLE' => 'FAIL',
            default => 'UNKNOWN',
        };
        if (in_array($resource['freshness'], ['STALE', 'UNKNOWN'], true) && $result === 'PASS') {
            $result = $definition['freshness']['stale_result'];
        }
        if ($definition['resource']['require_positive_capacity'] && $result === 'PASS') {
            $result = $resource['available_capacity'] === null ? 'UNKNOWN' : ($resource['available_capacity'] > 0 ? 'PASS' : 'FAIL');
        }

        return ['rule_id' => 'DS-RESOURCE-001', 'result' => $result, 'observed' => $resource, 'reason' => 'Resource state, capacity and freshness evaluated.'];
    }
}
