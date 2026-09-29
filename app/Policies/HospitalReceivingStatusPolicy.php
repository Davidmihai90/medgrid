<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalReceivingStatus;
use App\Models\User;

class HospitalReceivingStatusPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalReceivingStatus $status): bool
    {
        return $this->access->allows($user, $status->hospital, Permissions::HospitalAvailabilityView);
    }
}
