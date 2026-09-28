<?php

namespace App\Models;

use App\Domain\Clinical\Enums\AssessmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'patient_encounter_id', 'assessment_template_version_id', 'status', 'started_at', 'completed_at', 'created_by', 'completed_by'])] class Assessment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => AssessmentStatus::class, 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplateVersion::class, 'assessment_template_version_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }
}
