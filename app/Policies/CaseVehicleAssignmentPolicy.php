<?php

namespace App\Policies;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\CaseVehicleAssignment;
use App\Models\User;

class CaseVehicleAssignmentPolicy
{
    public function __construct(private CurrentOrganization $current) {}

    private function owns(CaseVehicleAssignment $assignment): bool
    {
        return $this->current->get()?->id === $assignment->organization_id;
    }

    public function deliver(User $user, CaseVehicleAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->hasPermission(Permissions::AssignmentsAcknowledge, $this->current->get());
    }

    public function accept(User $user, CaseVehicleAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->hasPermission(Permissions::AssignmentsAccept, $this->current->get());
    }

    public function cancel(User $user, CaseVehicleAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->hasPermission(Permissions::AssignmentsCancel, $this->current->get());
    }

    public function reassign(User $user, CaseVehicleAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->hasPermission(Permissions::AssignmentsReassign, $this->current->get());
    }
}
