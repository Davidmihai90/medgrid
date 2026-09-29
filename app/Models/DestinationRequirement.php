<?php

namespace App\Models;

use App\Domain\Destination\Enums\DestinationRequirementImportance;
use App\Domain\Destination\Enums\DestinationRequirementSource;
use App\Domain\Destination\Enums\DestinationRequirementStatus;
use App\Domain\Destination\Enums\DestinationRequirementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'emergency_case_id', 'patient_encounter_id', 'type', 'target_code', 'importance', 'source', 'status', 'notes', 'entered_by', 'confirmed_by', 'supersedes_requirement_id'])]
class DestinationRequirement extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['type' => DestinationRequirementType::class, 'importance' => DestinationRequirementImportance::class, 'source' => DestinationRequirementSource::class, 'status' => DestinationRequirementStatus::class];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }

    public function emergencyCase(): BelongsTo
    {
        return $this->belongsTo(EmergencyCase::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_requirement_id');
    }
}
