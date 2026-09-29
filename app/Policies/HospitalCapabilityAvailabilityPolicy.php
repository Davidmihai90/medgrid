<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalCapabilityAvailability;
use App\Models\User;

class HospitalCapabilityAvailabilityPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalCapabilityAvailability $availability): bool
    {
        return $this->access->allows($user, $availability->hospital, Permissions::HospitalAvailabilityView);
    }
}
