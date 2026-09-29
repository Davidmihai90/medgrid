<?php

namespace App\Domain\Hospitals\Services;

use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\Hospital;
use App\Models\User;

class HospitalAccess
{
    public function __construct(private CurrentOrganization $current) {}

    public function allows(User $user, Hospital $hospital, string $permission): bool
    {
        $organization = $this->current->get();

        if (! $organization || $hospital->organization_id !== $organization->id || ! $hospital->active || $hospital->status === HospitalStatus::Inactive) {
            return false;
        }

        if (! $user->hasPermission($permission, $organization)) {
            return false;
        }

        return $user->isPlatformSuperAdministrator() || $hospital->users()->whereKey($user->id)->exists();
    }

    public function accessibleQuery(User $user, string $permission)
    {
        $organization = $this->current->get();
        $query = Hospital::query()
            ->where('organization_id', $organization?->id)
            ->where('active', true)
            ->where('status', '!=', HospitalStatus::Inactive->value);

        if (! $user->isPlatformSuperAdministrator()) {
            $query->whereHas('users', fn ($access) => $access->whereKey($user->id));
        }

        if (! $organization || ! $user->hasPermission($permission, $organization)) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function ensure(User $user, Hospital $hospital, string $permission): void
    {
        abort_unless($this->allows($user, $hospital, $permission), 404);
    }
}
