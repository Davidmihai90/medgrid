<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalResourceState;
use App\Models\User;

class HospitalResourceStatePolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalResourceState $state): bool
    {
        return $this->access->allows($user, $state->hospital, Permissions::HospitalResourcesView);
    }

    public function update(User $user, HospitalResourceState $state): bool
    {
        return $this->access->allows($user, $state->hospital, Permissions::HospitalResourcesUpdate);
    }
}
