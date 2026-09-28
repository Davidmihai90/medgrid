<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Enums\ObservationSource;
use App\Domain\Clinical\Enums\SyncOutcome;
use App\Domain\Dispatch\Exceptions\DispatchConflict;
use App\Domain\Identity\Support\Permissions;
use App\Http\Requests\Clinical\StoreVitalRequest;
use App\Models\PatientEncounter;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;
use ValueError;

class SyncService
{
    public function __construct(private ClinicalService $clinical) {}

    public function process(array $operations, User $actor, string $organizationId, ?string $sessionId): array
    {
        return collect($operations)
            ->map(fn (array $operation) => $this->processOne($operation, $actor, $organizationId, $sessionId))
            ->all();
    }

    private function processOne(array $operation, User $actor, string $organizationId, ?string $sessionId): array
    {
        $hash = $this->operationHash($operation);
        $existing = SyncOperation::query()
            ->where('organization_id', $organizationId)
            ->where('operation_id', $operation['operation_id'])
            ->first();

        if ($existing) {
            if (! hash_equals($existing->payload_hash, $hash)) {
                return $this->result($operation['operation_id'], SyncOutcome::Conflict, $existing->resource_id);
            }

            $outcome = $existing->outcome === SyncOutcome::Accepted
                ? SyncOutcome::Duplicate
                : $existing->outcome;

            return $this->result($operation['operation_id'], $outcome, $existing->resource_id);
        }

        try {
            $encounter = PatientEncounter::query()
                ->where('organization_id', $organizationId)
                ->findOrFail($operation['target_id']);

            if (! $actor->hasPermission(Permissions::SyncSubmit, $encounter->emergencyCase->organization)) {
                throw new AuthorizationException('Sync permission is required.');
            }

            $resource = match ($operation['operation_type']) {
                'VITAL_CREATE' => $this->recordVital($encounter, $actor, $operation),
                'CONDITION_UPDATE' => $this->updateCondition($encounter, $actor, $operation),
                default => throw ValidationException::withMessages([
                    'operation_type' => 'Unsupported offline operation.',
                ]),
            };
            $outcome = SyncOutcome::Accepted;
        } catch (DispatchConflict) {
            $resource = null;
            $outcome = SyncOutcome::Conflict;
        } catch (AuthorizationException|ModelNotFoundException|ValidationException|ValueError) {
            $resource = null;
            $outcome = SyncOutcome::Rejected;
        } catch (Throwable $exception) {
            report($exception);
            $resource = null;
            $outcome = SyncOutcome::Rejected;
        }

        $record = SyncOperation::create([
            'organization_id' => $organizationId,
            'operation_id' => $operation['operation_id'],
            'operation_type' => $operation['operation_type'],
            'target_type' => PatientEncounter::class,
            'target_id' => $operation['target_id'],
            'entity_version' => $operation['entity_version'] ?? null,
            'payload_hash' => $hash,
            'outcome' => $outcome,
            'resource_type' => $resource?->getMorphClass(),
            'resource_id' => $resource?->getKey(),
            'device_id' => $operation['device_id'] ?? null,
            'session_id' => $sessionId,
            'submitted_by' => $actor->id,
            'captured_at' => $operation['captured_at'],
            'processed_at' => now(),
        ]);

        return $this->result($record->operation_id, $outcome, $record->resource_id);
    }

    private function recordVital(PatientEncounter $encounter, User $actor, array $operation): Model
    {
        $data = $operation['payload'] + [
            'operation_id' => $operation['operation_id'],
            'source' => ObservationSource::OfflineSync->value,
        ];
        $request = StoreVitalRequest::create('/', 'POST', $data);
        $validator = Validator::make($data, $request->rules());

        foreach ($request->after() as $callback) {
            $validator->after($callback);
        }

        $validated = $validator->validate();

        return $this->clinical->recordVital($encounter, $actor, $validated);
    }

    private function updateCondition(PatientEncounter $encounter, User $actor, array $operation): Model
    {
        if (! isset($operation['entity_version'], $operation['payload']['condition_level'])) {
            throw ValidationException::withMessages([
                'entity_version' => 'Condition updates require an entity version and condition level.',
            ]);
        }

        return $this->clinical->updateCondition(
            $encounter,
            $actor,
            ConditionLevel::from($operation['payload']['condition_level']),
            (int) $operation['entity_version'],
        );
    }

    private function operationHash(array $operation): string
    {
        $semanticOperation = [
            'operation_type' => $operation['operation_type'],
            'target_id' => $operation['target_id'],
            'entity_version' => $operation['entity_version'] ?? null,
            'payload' => $operation['payload'],
        ];

        return hash('sha256', json_encode($this->canonicalize($semanticOperation), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    private function result(string $operationId, SyncOutcome $outcome, ?string $resourceId): array
    {
        return [
            'operation_id' => $operationId,
            'outcome' => $outcome->value,
            'resource_id' => $resourceId,
        ];
    }
}
