<?php

use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $m1 = [Permissions::CasesView, Permissions::CasesCreate, Permissions::CasesUpdate, Permissions::CasesAssign, Permissions::CasesClose, Permissions::VehiclesView, Permissions::VehiclesManage, Permissions::CrewView, Permissions::CrewManage, Permissions::AssignmentsView, Permissions::AssignmentsCreate, Permissions::AssignmentsCancel, Permissions::AssignmentsReassign, Permissions::AssignmentsAcknowledge, Permissions::AssignmentsAccept];

    public function up(): void
    {
        $now = now();
        foreach ($this->m1 as $slug) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::ulid(), 'name' => ucwords(str_replace(['.', '-'], ' ', $slug)), 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        }$all = $this->m1;
        $dispatch = [Permissions::CasesView, Permissions::CasesCreate, Permissions::CasesUpdate, Permissions::CasesAssign, Permissions::CasesClose, Permissions::VehiclesView, Permissions::CrewView, Permissions::AssignmentsView, Permissions::AssignmentsCreate, Permissions::AssignmentsCancel, Permissions::AssignmentsReassign];
        $crew = [Permissions::CasesView, Permissions::VehiclesView, Permissions::CrewView, Permissions::AssignmentsView, Permissions::AssignmentsAcknowledge, Permissions::AssignmentsAccept];
        $map = [RoleSlugs::OrganizationAdministrator => $all, RoleSlugs::Dispatcher => $dispatch, RoleSlugs::MedicalCoordinator => [Permissions::CasesView, Permissions::CasesUpdate], RoleSlugs::AmbulancePhysician => $crew, RoleSlugs::Paramedic => $crew, RoleSlugs::Nurse => $crew, RoleSlugs::AmbulanceDriver => $crew];
        foreach ($map as $roleSlug => $grants) {
            $role = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $role) {
                continue;
            }foreach ($grants as $slug) {
                $permission = DB::table('permissions')->where('slug', $slug)->value('id');
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', $this->m1)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
