<?php

namespace App\Models;

use App\Domain\Hospitals\Enums\HospitalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'code', 'name', 'short_name', 'status', 'address', 'latitude', 'longitude', 'timezone', 'phone', 'active', 'version'])]
class Hospital extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => HospitalStatus::class, 'active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(HospitalDepartment::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(HospitalCapability::class);
    }

    public function receivingStatuses(): HasMany
    {
        return $this->hasMany(HospitalReceivingStatus::class);
    }

    public function resourceStates(): HasMany
    {
        return $this->hasMany(HospitalResourceState::class);
    }

    public function restrictions(): HasMany
    {
        return $this->hasMany(HospitalOperationalRestriction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(HospitalCaseNotification::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hospital_user_access')->withPivot(['id', 'organization_id']);
    }
}
