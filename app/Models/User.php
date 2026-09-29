<?php

namespace App\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Support\RoleSlugs;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'status', 'email_verified_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['id', 'status'])->withTimestamps();
    }

    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Hospital::class, 'hospital_user_access')->withPivot(['id', 'organization_id']);
    }

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'platform_role_user')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isPlatformSuperAdministrator(): bool
    {
        return $this->isActive() && $this->platformRoles()->where('slug', RoleSlugs::SuperAdministrator)->exists();
    }

    public function hasPermission(string $permission, ?Organization $organization = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->platformRoles()->whereHas('permissions', fn ($query) => $query->where('slug', $permission))->exists()) {
            return true;
        }

        if (! $organization || $organization->status !== OrganizationStatus::Active) {
            return false;
        }

        return $this->memberships()
            ->where('organization_id', $organization->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }
}
