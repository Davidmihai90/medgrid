<?php

namespace App\Models;

use App\Domain\Clinical\Enums\PatientIdentityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'identity_status', 'first_name', 'last_name', 'date_of_birth', 'estimated_age_min', 'estimated_age_max', 'sex', 'national_identifier', 'phone', 'version', 'created_by', 'updated_by'])]
class Patient extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['identity_status' => PatientIdentityStatus::class, 'date_of_birth' => 'immutable_date'];
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(PatientEncounter::class);
    }

    public function scopeForOrganization(Builder $q, Organization $o): Builder
    {
        return $q->where('organization_id', $o->id);
    }
}
