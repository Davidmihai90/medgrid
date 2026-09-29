<?php

namespace App\Policies;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalDepartment;
use App\Models\User;

class HospitalDepartmentPolicy
{
    public function __construct(private HospitalAccess $access) {}

    public function view(User $user, HospitalDepartment $department): bool
    {
        return $this->access->allows($user, $department->hospital, Permissions::HospitalDepartmentsView);
    }

    public function manage(User $user, HospitalDepartment $department): bool
    {
        return $this->access->allows($user, $department->hospital, Permissions::HospitalDepartmentsManage);
    }
}
