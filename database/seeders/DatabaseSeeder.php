<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Support\Permissions as PermissionNames;
use App\Domain\Identity\Support\RoleSlugs;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Synthetic MEDGRID seed data is restricted to local/testing environments.');
        }
        $password = env('MEDGRID_DEMO_PASSWORD');
        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException('Set MEDGRID_DEMO_PASSWORD (12+ characters) in the local untracked .env before seeding.');
        }
        $permissions = collect(PermissionNames::All)->mapWithKeys(fn ($slug) => [$slug => Permission::updateOrCreate(['slug' => $slug], ['name' => ucwords(str_replace(['.', '-'], ' ', $slug))])]);
        $definitions = [
            RoleSlugs::SuperAdministrator => ['Super Administrator', 'PLATFORM', PermissionNames::All],
            RoleSlugs::OrganizationAdministrator => ['Organization Administrator', 'ORGANIZATION', PermissionNames::All],
            RoleSlugs::Dispatcher => ['Dispatcher', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::CasesCreate, PermissionNames::CasesUpdate, PermissionNames::CasesAssign, PermissionNames::CasesClose, PermissionNames::VehiclesView, PermissionNames::CrewView, PermissionNames::AssignmentsView, PermissionNames::AssignmentsCreate, PermissionNames::AssignmentsCancel, PermissionNames::AssignmentsReassign]],
            RoleSlugs::MedicalCoordinator => ['Medical Coordinator', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::CasesUpdate]],
            RoleSlugs::AmbulancePhysician => ['Ambulance Physician', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::VehiclesView, PermissionNames::CrewView, PermissionNames::AssignmentsView, PermissionNames::AssignmentsAcknowledge, PermissionNames::AssignmentsAccept, ...PermissionNames::M2]],
            RoleSlugs::Paramedic => ['Paramedic', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::VehiclesView, PermissionNames::CrewView, PermissionNames::AssignmentsView, PermissionNames::AssignmentsAcknowledge, PermissionNames::AssignmentsAccept, ...PermissionNames::M2]],
            RoleSlugs::Nurse => ['Nurse', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::VehiclesView, PermissionNames::CrewView, PermissionNames::AssignmentsView, PermissionNames::AssignmentsAcknowledge, PermissionNames::AssignmentsAccept, ...PermissionNames::M2]],
            RoleSlugs::AmbulanceDriver => ['Ambulance Driver', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::CasesView, PermissionNames::VehiclesView, PermissionNames::CrewView, PermissionNames::AssignmentsView, PermissionNames::AssignmentsAcknowledge, PermissionNames::AssignmentsAccept, PermissionNames::AmbulanceWorkflowUpdate, PermissionNames::EncountersView]],
            RoleSlugs::HospitalOperator => ['Hospital Operator', 'ORGANIZATION', [PermissionNames::DashboardView]],
            RoleSlugs::HospitalResourceManager => ['Hospital Resource Manager', 'ORGANIZATION', [PermissionNames::DashboardView]],
            RoleSlugs::Doctor => ['Doctor', 'ORGANIZATION', [PermissionNames::DashboardView]],
            RoleSlugs::Auditor => ['Auditor', 'ORGANIZATION', [PermissionNames::DashboardView, PermissionNames::AuditView]],
        ];
        $roles = [];
        foreach ($definitions as $slug => [$name,$scope,$grants]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'scope' => $scope, 'is_system' => true]);
            $role->permissions()->sync(collect($grants)->map(fn ($p) => $permissions[$p]->id));
            $roles[$slug] = $role;
        }
        $orgA = Organization::updateOrCreate(['slug' => 'demo-emergency-service'], ['name' => 'Synthetic Metro Emergency Service', 'type' => 'AMBULANCE_SERVICE', 'status' => OrganizationStatus::Active, 'timezone' => 'Europe/Bucharest']);
        $orgB = Organization::updateOrCreate(['slug' => 'demo-hospital-network'], ['name' => 'Synthetic Regional Hospital Network', 'type' => 'HOSPITAL_NETWORK', 'status' => OrganizationStatus::Active, 'timezone' => 'Europe/Bucharest']);
        $super = $this->user('Platform Demo Administrator', 'platform.admin@medgrid.test', $password);
        $super->platformRoles()->sync([$roles[RoleSlugs::SuperAdministrator]->id]);
        $this->member($orgA, 'Alex Demo Admin', 'org.admin.a@medgrid.test', RoleSlugs::OrganizationAdministrator, $roles, $password);
        $this->member($orgA, 'Daria Demo Dispatcher', 'dispatcher.a@medgrid.test', RoleSlugs::Dispatcher, $roles, $password);
        $this->member($orgA, 'Mara Demo Coordinator', 'coordinator.a@medgrid.test', RoleSlugs::MedicalCoordinator, $roles, $password);
        $this->member($orgA, 'Paul Demo Paramedic', 'paramedic.a@medgrid.test', RoleSlugs::Paramedic, $roles, $password);
        $this->member($orgA, 'Victor Demo Driver', 'driver.a@medgrid.test', RoleSlugs::AmbulanceDriver, $roles, $password);
        $this->member($orgB, 'Bianca Demo Admin', 'org.admin.b@medgrid.test', RoleSlugs::OrganizationAdministrator, $roles, $password);
        $this->member($orgB, 'Oana Demo Operator', 'hospital.operator.b@medgrid.test', RoleSlugs::HospitalOperator, $roles, $password);
        $this->member($orgB, 'Radu Demo Resources', 'resources.b@medgrid.test', RoleSlugs::HospitalResourceManager, $roles, $password);
        $this->member($orgB, 'Drina Demo Doctor', 'doctor.b@medgrid.test', RoleSlugs::Doctor, $roles, $password);
        $this->member($orgB, 'Aurel Demo Auditor', 'auditor.b@medgrid.test', RoleSlugs::Auditor, $roles, $password);
        $this->call(AmbulanceDemoSeeder::class);
    }

    private function user(string $name, string $email, string $password): User
    {
        return User::updateOrCreate(['email' => $email], ['name' => $name, 'status' => UserStatus::Active, 'email_verified_at' => now(), 'password' => Hash::make($password)]);
    }

    private function member(Organization $org, string $name, string $email, string $role, array $roles, string $password): void
    {
        $user = $this->user($name, $email, $password);
        $membership = OrganizationMembership::updateOrCreate(['organization_id' => $org->id, 'user_id' => $user->id], ['status' => MembershipStatus::Active]);
        $membership->roles()->sync([$roles[$role]->id]);
    }
}
