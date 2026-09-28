<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Dispatch\Exceptions\DispatchConflict;
use App\Models\CaseVehicleAssignment;
use App\Models\EmergencyCase;
use App\Models\User;

class AmbulanceAccess
{
    public function activeAssignment(EmergencyCase $case, User $actor, string $permission): CaseVehicleAssignment
    {
        if (! $actor->hasPermission($permission, $case->organization)) {
            throw new DispatchConflict('You are not authorized for this ambulance action.');
        }
        $assignment = CaseVehicleAssignment::query()
            ->where('emergency_case_id', $case->id)
            ->where('organization_id', $case->organization_id)
            ->where('status', AssignmentStatus::Accepted)
            ->whereHas('vehicle.crewAssignments', fn ($query) => $query->where('user_id', $actor->id)->whereNull('ended_at'))
            ->with('vehicle')
            ->first();
        if (! $assignment) {
            throw new DispatchConflict('An accepted mission and active crew membership are required.');
        }

        return $assignment;
    }
}
