<?php

namespace App\Policies;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\EmergencyCase;
use App\Models\User;

class EmergencyCasePolicy
{
    public function __construct(private CurrentOrganization $current) {}

    private function owns(EmergencyCase $case): bool
    {
        return $this->current->get()?->id === $case->organization_id;
    }

    public function viewAny(User $u): bool
    {
        return $u->hasPermission(Permissions::CasesView, $this->current->get());
    }

    public function view(User $u, EmergencyCase $c): bool
    {
        return $this->owns($c) && $u->hasPermission(Permissions::CasesView, $this->current->get());
    }

    public function create(User $u): bool
    {
        return $u->hasPermission(Permissions::CasesCreate, $this->current->get());
    }

    public function update(User $u, EmergencyCase $c): bool
    {
        return $this->owns($c) && $u->hasPermission(Permissions::CasesUpdate, $this->current->get());
    }

    public function assign(User $u, EmergencyCase $c): bool
    {
        return $this->owns($c) && $u->hasPermission(Permissions::CasesAssign, $this->current->get());
    }
}
