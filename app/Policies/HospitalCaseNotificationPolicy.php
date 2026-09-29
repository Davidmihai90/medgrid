<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalCaseNotification;
use App\Models\User;

class HospitalCaseNotificationPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalCaseNotification $notification): bool
    {
        return $this->access->allows($user, $notification->hospital, Permissions::HospitalIncomingView);
    }

    public function acknowledge(User $user, HospitalCaseNotification $notification): bool
    {
        return $this->access->allows($user, $notification->hospital, Permissions::HospitalIncomingAcknowledge);
    }
}
