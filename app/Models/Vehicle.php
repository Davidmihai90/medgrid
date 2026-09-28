<?php

namespace App\Models;

use App\Domain\Dispatch\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'callsign', 'display_name', 'registration_number', 'vehicle_type', 'status', 'is_active'])]
class Vehicle extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => VehicleStatus::class, 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function crewAssignments(): HasMany
    {
        return $this->hasMany(VehicleCrewAssignment::class);
    }

    public function caseAssignments(): HasMany
    {
        return $this->hasMany(CaseVehicleAssignment::class);
    }

    public function scopeForOrganization(Builder $q, Organization $o): Builder
    {
        return $q->where('organization_id', $o->id);
    }
}
