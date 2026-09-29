<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalCapability;
use App\Models\User;

class HospitalCapabilityPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalCapability $capability): bool
    {
        return $this->access->allows($user, $capability->hospital, Permissions::HospitalCapabilitiesView);
    }

    public function manage(User $user, HospitalCapability $capability): bool
    {
        return $this->access->allows($user, $capability->hospital, Permissions::HospitalCapabilitiesManage);
    }

    public function updateAvailability(User $user, HospitalCapability $capability): bool
    {
        return $this->access->allows($user, $capability->hospital, Permissions::HospitalAvailabilityUpdate);
    }
}
