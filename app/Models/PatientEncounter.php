<?php

namespace App\Models;

use App\Domain\Clinical\Enums\ClinicalKnowledgeStatus;
use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Enums\EncounterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'emergency_case_id', 'patient_id', 'encounter_index', 'encounter_number', 'status', 'condition_level', 'allergy_status', 'medication_status', 'history_status', 'contact_at', 'assessment_started_at', 'assessment_completed_at', 'version', 'destination_version', 'created_by', 'updated_by'])]
class PatientEncounter extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => EncounterStatus::class, 'condition_level' => ConditionLevel::class, 'allergy_status' => ClinicalKnowledgeStatus::class, 'medication_status' => ClinicalKnowledgeStatus::class, 'history_status' => ClinicalKnowledgeStatus::class, 'contact_at' => 'immutable_datetime', 'assessment_started_at' => 'immutable_datetime', 'assessment_completed_at' => 'immutable_datetime'];
    }

    public function emergencyCase(): BelongsTo
    {
        return $this->belongsTo(EmergencyCase::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(VitalObservation::class)->orderByDesc('measured_at');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class)->orderByDesc('recorded_at');
    }

    public function destinationRequirements(): HasMany
    {
        return $this->hasMany(DestinationRequirement::class);
    }

    public function destinationEvaluations(): HasMany
    {
        return $this->hasMany(DestinationEvaluation::class);
    }

    public function destinationSelections(): HasMany
    {
        return $this->hasMany(DestinationSelection::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }
}
