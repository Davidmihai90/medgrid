<?php

namespace Tests\Concerns;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesAuthorizationContext
{
    protected function context(array $permissions = [], array $userState = [], MembershipStatus $membershipStatus = MembershipStatus::Active): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(array_merge(['status' => UserStatus::Active], $userState));
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => $membershipStatus]);
        $role = Role::create(['name' => 'Test Role', 'slug' => 'test-'.Str::lower((string) Str::ulid()), 'scope' => 'ORGANIZATION', 'is_system' => false]);
        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission);
        }
        $membership->roles()->attach($role);

        return compact('organization', 'user', 'membership', 'role');
    }
}
