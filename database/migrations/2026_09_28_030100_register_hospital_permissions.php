<?php

use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (Permissions::M3 as $slug) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::ulid(), 'name' => ucwords(str_replace(['.', '-'], ' ', $slug)), 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        }

        $all = [RoleSlugs::OrganizationAdministrator];
        $operations = [RoleSlugs::HospitalOperator];
        $resources = [RoleSlugs::HospitalResourceManager];
        $clinical = [RoleSlugs::Doctor, RoleSlugs::Nurse];

        foreach ($all as $role) {
            $this->grant($role, Permissions::M3, $now);
        }
        foreach ($operations as $role) {
            $this->grant($role, [Permissions::HospitalsView, Permissions::HospitalDepartmentsView, Permissions::HospitalCapabilitiesView, Permissions::HospitalAvailabilityView, Permissions::HospitalAvailabilityUpdate, Permissions::HospitalResourcesView, Permissions::HospitalIncomingView, Permissions::HospitalIncomingAcknowledge], $now);
        }
        foreach ($resources as $role) {
            $this->grant($role, [Permissions::HospitalsView, Permissions::HospitalDepartmentsView, Permissions::HospitalCapabilitiesView, Permissions::HospitalAvailabilityView, Permissions::HospitalAvailabilityUpdate, Permissions::HospitalResourcesView, Permissions::HospitalResourcesUpdate, Permissions::HospitalIncomingView], $now);
        }
        foreach ($clinical as $role) {
            $this->grant($role, [Permissions::HospitalsView, Permissions::HospitalDepartmentsView, Permissions::HospitalCapabilitiesView, Permissions::HospitalAvailabilityView, Permissions::HospitalResourcesView, Permissions::HospitalIncomingView, Permissions::HospitalIncomingAcknowledge], $now);
        }
    }

    private function grant(string $roleSlug, array $slugs, $now): void
    {
        $role = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if (! $role) {
            return;
        }
        foreach ($slugs as $slug) {
            $permission = DB::table('permissions')->where('slug', $slug)->value('id');
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', Permissions::M3)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
