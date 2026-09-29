<?php

namespace App\Models;

use App\Domain\Destination\Enums\DestinationEvaluationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['organization_id', 'emergency_case_id', 'patient_encounter_id', 'destination_requirement_set_id', 'destination_rule_set_version_id', 'status', 'reference_time', 'started_at', 'completed_at', 'failure_reason', 'requested_by', 'idempotency_key'])]
class DestinationEvaluation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updating(function (self $evaluation): void {
            $terminal = [DestinationEvaluationStatus::Completed, DestinationEvaluationStatus::Failed, DestinationEvaluationStatus::Cancelled];
            if (in_array($evaluation->getRawOriginal('status'), array_map(fn ($status) => $status->value, $terminal), true)) {
                throw new LogicException('Terminal destination evaluations are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Destination evaluations are append-only.'));
    }

    protected function casts(): array
    {
        return ['status' => DestinationEvaluationStatus::class, 'reference_time' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(DestinationRequirementSet::class, 'destination_requirement_set_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(DestinationRuleSetVersion::class, 'destination_rule_set_version_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(DestinationCandidateEvaluation::class);
    }
}
