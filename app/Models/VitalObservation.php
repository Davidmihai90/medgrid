<?php

namespace App\Models;

use App\Domain\Clinical\Enums\ObservationSource;
use App\Domain\Clinical\Enums\VitalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'patient_encounter_id', 'type', 'value_numeric', 'secondary_value_numeric', 'value_text', 'unit', 'gcs_eye', 'gcs_verbal', 'gcs_motor', 'gcs_total', 'gcs_total_derived', 'measured_at', 'recorded_at', 'recorded_by', 'source', 'device_identifier', 'operation_id', 'supersedes_observation_id', 'superseded_at', 'superseded_by', 'correction_reason'])]
class VitalObservation extends Model
{
    use HasUlids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['type' => VitalType::class, 'source' => ObservationSource::class, 'value_numeric' => 'decimal:4', 'secondary_value_numeric' => 'decimal:4', 'gcs_total_derived' => 'boolean', 'measured_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime', 'superseded_at' => 'immutable_datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_observation_id');
    }
}
