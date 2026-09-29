<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use App\Models\Organization;
use App\Models\User;

class ApplicationAreaRegistry
{
    public function all(): array
    {
        return [
            'dispatch' => ['label' => 'Dispatch', 'description' => 'Dispatch operating shell', 'icon' => 'radio', 'roles' => [RoleSlugs::Dispatcher, RoleSlugs::OrganizationAdministrator]],
            'ambulance' => ['label' => 'Ambulance', 'description' => 'Tablet-ready crew shell', 'icon' => 'ambulance', 'roles' => [RoleSlugs::AmbulancePhysician, RoleSlugs::Paramedic, RoleSlugs::Nurse, RoleSlugs::AmbulanceDriver]],
            'hospital' => ['label' => 'Hospital', 'description' => 'Receiving facility shell', 'icon' => 'hospital', 'roles' => [RoleSlugs::HospitalOperator, RoleSlugs::HospitalResourceManager, RoleSlugs::Doctor, RoleSlugs::Nurse]],
            'medical' => ['label' => 'Medical coordination', 'description' => 'Destination coordination workspace', 'icon' => 'stethoscope', 'permissions' => [Permissions::DestinationRequirementsView]],
            'control' => ['label' => 'Control center', 'description' => 'Network oversight shell', 'icon' => 'layout-dashboard', 'roles' => [RoleSlugs::OrganizationAdministrator, RoleSlugs::Dispatcher, RoleSlugs::MedicalCoordinator]],
            'admin' => ['label' => 'Administration', 'description' => 'Identity and system controls', 'icon' => 'settings-2', 'permissions' => [Permissions::OrganizationsView, Permissions::UsersView, Permissions::RolesView, Permissions::AuditView, Permissions::SystemHealthView]],
        ];
    }

    public function allowed(User $user, ?Organization $organization): array
    {
        if (! $organization) {
            return [];
        }
        if ($user->isPlatformSuperAdministrator()) {
            return $this->all();
        }
        $roleSlugs = $user->memberships()->where('organization_id', $organization->id)->whereHas('roles')->with('roles:id,slug')->first()?->roles->pluck('slug')->all() ?? [];

        return array_filter($this->all(), fn ($area) => isset($area['permissions']) ? collect($area['permissions'])->contains(fn ($permission) => $user->hasPermission($permission, $organization)) : count(array_intersect($area['roles'], $roleSlugs)) > 0);
    }

    public function canAccess(User $user, Organization $organization, string $area): bool
    {
        return isset($this->allowed($user, $organization)[$area]);
    }
}
