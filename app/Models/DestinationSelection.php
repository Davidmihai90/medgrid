<?php

namespace App\Models;

use App\Domain\Destination\Enums\DestinationSelectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'emergency_case_id', 'patient_encounter_id', 'destination_evaluation_id', 'destination_candidate_evaluation_id', 'hospital_id', 'selection_type', 'reason', 'selected_by', 'supersedes_selection_id', 'superseded_at', 'superseded_by', 'idempotency_key'])]
class DestinationSelection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['selection_type' => DestinationSelectionType::class, 'superseded_at' => 'immutable_datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(DestinationEvaluation::class, 'destination_evaluation_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(DestinationCandidateEvaluation::class, 'destination_candidate_evaluation_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_selection_id');
    }
}
