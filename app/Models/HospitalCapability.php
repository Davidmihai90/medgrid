<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'hospital_id', 'hospital_department_id', 'capability_definition_id', 'enabled', 'notes', 'effective_from', 'effective_until'])]
class HospitalCapability extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HospitalDepartment::class, 'hospital_department_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CapabilityDefinition::class, 'capability_definition_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(HospitalCapabilityAvailability::class);
    }
}
