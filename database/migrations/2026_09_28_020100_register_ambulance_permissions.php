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
        foreach (Permissions::M2 as $slug) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::ulid(), 'name' => ucwords(str_replace(['.', '-'], ' ', $slug)), 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        }$clinical = [RoleSlugs::OrganizationAdministrator, RoleSlugs::AmbulancePhysician, RoleSlugs::Paramedic, RoleSlugs::Nurse];
        $driver = [Permissions::AmbulanceWorkflowUpdate, Permissions::EncountersView];
        foreach ($clinical as $roleSlug) {
            $this->grant($roleSlug, Permissions::M2, $now);
        }$this->grant(RoleSlugs::AmbulanceDriver, $driver, $now);
    }

    private function grant(string $roleSlug, array $slugs, $now): void
    {
        $role = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if (! $role) {
            return;
        }foreach ($slugs as $slug) {
            $permission = DB::table('permissions')->where('slug', $slug)->value('id');
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', Permissions::M2)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
