<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'emergency_case_id', 'patient_encounter_id', 'created_by', 'captured_at'])]
class DestinationRequirementSet extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['captured_at' => 'immutable_datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(DestinationRequirement::class, 'destination_requirement_set_items')->withPivot(['id', 'created_at']);
    }
}
