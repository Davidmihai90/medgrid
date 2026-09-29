<?php

namespace App\Models;

use App\Domain\Organizations\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'type', 'status', 'timezone'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }
}
