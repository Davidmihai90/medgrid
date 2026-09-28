<?php

namespace App\Policies;

use App\Domain\Dispatch\Enums\AssignmentStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\PatientEncounter;
use App\Models\User;

class PatientEncounterPolicy
{
    public function __construct(private CurrentOrganization $current) {}

    private function crew(User $user, PatientEncounter $encounter, string $permission): bool
    {
        return $this->current->get()?->id === $encounter->organization_id && $user->hasPermission($permission, $this->current->get()) && $encounter->emergencyCase->assignments()->where('status', AssignmentStatus::Accepted)->whereHas('vehicle.crewAssignments', fn ($q) => $q->where('user_id', $user->id)->whereNull('ended_at'))->exists();
    }

    public function view(User $u, PatientEncounter $e): bool
    {
        return $this->crew($u, $e, Permissions::EncountersView);
    }

    public function update(User $u, PatientEncounter $e): bool
    {
        return $this->crew($u, $e, Permissions::EncountersUpdate);
    }

    public function recordVitals(User $u, PatientEncounter $e): bool
    {
        return $this->crew($u, $e, Permissions::VitalsCreate);
    }

    public function criticalMode(User $u, PatientEncounter $e): bool
    {
        return $this->view($u, $e);
    }
}
