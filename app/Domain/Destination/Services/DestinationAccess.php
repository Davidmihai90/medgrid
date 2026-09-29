<?php

namespace App\Domain\Destination\Services;

use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\Hospital;
use App\Models\PatientEncounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DestinationAccess
{
    public function __construct(private CurrentOrganization $current) {}

    public function ensureEncounter(User $user, PatientEncounter $encounter, string $permission): void
    {
        $organization = $this->current->get();
        abort_unless($organization && $encounter->organization_id === $organization->id, 404);
        abort_unless($user->hasPermission($permission, $organization), 403);
    }

    public function hospitalQuery(User $user): Builder
    {
        $organization = $this->current->get();
        $query = Hospital::query()
            ->where('active', true)
            ->where('status', '!=', HospitalStatus::Inactive->value)
            ->whereHas('destinationAccessOrganizations', fn (Builder $access) => $access
                ->whereKey($organization?->id)
                ->where('destination_hospital_access.active', true));

        if (! $organization || ! $user->hasPermission(Permissions::DestinationEvaluationsView, $organization)) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function ensureHospital(User $user, Hospital $hospital): void
    {
        abort_unless($this->hospitalQuery($user)->whereKey($hospital->id)->exists(), 404);
    }
}
