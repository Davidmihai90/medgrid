<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;
use App\Models\User;

class HospitalPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalsView);
    }

    public function manage(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalsManage);
    }

    public function updateAvailability(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalAvailabilityUpdate);
    }

    public function updateResources(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalResourcesUpdate);
    }

    public function viewIncoming(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalIncomingView);
    }

    public function acknowledgeIncoming(User $user, Hospital $hospital): bool
    {
        return $this->access->allows($user, $hospital, Permissions::HospitalIncomingAcknowledge);
    }
}
