<?php

namespace App\Policies;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\User;

class UserPolicy
{
    public function __construct(private CurrentOrganization $current) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::UsersView, $this->current->get());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::UsersManage, $this->current->get());
    }

    public function view(User $user, User $subject): bool
    {
        $org = $this->current->get();

        return (bool) ($org && $user->hasPermission(Permissions::UsersView, $org) && ($user->isPlatformSuperAdministrator() || $subject->memberships()->where('organization_id', $org->id)->exists()));
    }

    public function update(User $user, User $subject): bool
    {
        $org = $this->current->get();

        return (bool) ($org && $user->hasPermission(Permissions::UsersManage, $org) && ($user->isPlatformSuperAdministrator() || $subject->memberships()->where('organization_id', $org->id)->exists()));
    }
}
