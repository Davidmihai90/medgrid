<?php

namespace Tests\Feature;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    public function test_ordinary_user_cannot_access_admin(): void
    {
        $ctx = $this->context([Permissions::DashboardView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin')->assertForbidden();
    }

    public function test_authorized_organization_admin_can_access_admin(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin')->assertOk();
    }

    public function test_platform_super_administrator_is_distinct_and_can_access_without_membership(): void
    {
        $organization = $this->context()['organization'];
        $superAdministrator = User::factory()->create();
        $platformRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => RoleSlugs::SuperAdministrator,
            'scope' => 'PLATFORM',
            'is_system' => true,
        ]);
        $superAdministrator->platformRoles()->attach($platformRole);

        $this->assertFalse($superAdministrator->memberships()->exists());
        $this->actingAs($superAdministrator)
            ->withSession(['current_organization_id' => $organization->id])
            ->get('/admin')
            ->assertOk();
    }

    public function test_auditor_with_permission_can_view_audit(): void
    {
        $ctx = $this->context([Permissions::AuditView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin/audit')->assertOk();
    }

    public function test_audit_access_requires_permission(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin/audit')->assertForbidden();
    }
}
