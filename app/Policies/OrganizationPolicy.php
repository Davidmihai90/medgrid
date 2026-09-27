<?php

namespace App\Policies;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function __construct(private CurrentOrganization $current) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::OrganizationsView, $this->current->get());
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isPlatformSuperAdministrator() || ($this->current->get()?->is($organization) && $user->hasPermission(Permissions::OrganizationsView, $organization));
    }

    public function create(User $user): bool
    {
        return $user->isPlatformSuperAdministrator();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isPlatformSuperAdministrator() || ($this->current->get()?->is($organization) && $user->hasPermission(Permissions::OrganizationsManage, $organization));
    }
}
