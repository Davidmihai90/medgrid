<?php

namespace Tests\Feature;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Identity\Support\RoleSlugs;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    public function test_user_creation_and_role_assignment_generate_audit_records(): void
    {
        $ctx = $this->context([Permissions::UsersView, Permissions::UsersManage]);
        $role = Role::create(['name' => 'Paramedic', 'slug' => 'paramedic-'.Str::lower((string) Str::ulid()), 'scope' => 'ORGANIZATION', 'is_system' => false]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->post(route('admin.users.store'), ['name' => 'Synthetic New User', 'email' => 'new.user@medgrid.test', 'password' => 'Strong!Local2026', 'password_confirmation' => 'Strong!Local2026', 'status' => 'ACTIVE', 'role_ids' => [$role->id]])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $ctx['organization']->id, 'action' => 'user.created']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $ctx['organization']->id, 'action' => 'organization_membership.created']);
        $this->assertSame(0, AuditLog::whereRaw("metadata::text ilike '%password%'")->count());
    }

    public function test_platform_administrator_status_change_is_audited(): void
    {
        $ctx = $this->context();
        $superAdministrator = User::factory()->create();
        $platformRole = Role::create(['name' => 'Super Administrator', 'slug' => RoleSlugs::SuperAdministrator, 'scope' => 'PLATFORM', 'is_system' => true]);
        $superAdministrator->platformRoles()->attach($platformRole);

        $this->actingAs($superAdministrator)
            ->withSession(['current_organization_id' => $ctx['organization']->id])
            ->put(route('admin.users.update', $ctx['user']), [
                'name' => $ctx['user']->name,
                'email' => $ctx['user']->email,
                'status' => 'SUSPENDED',
                'membership_status' => 'ACTIVE',
                'role_ids' => [$ctx['role']->id],
            ])->assertRedirect();

        $this->assertSame('SUSPENDED', $ctx['user']->refresh()->status->value);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $ctx['organization']->id,
            'actor_id' => $superAdministrator->id,
            'action' => 'user.updated',
            'resource_id' => $ctx['user']->id,
        ]);
    }

    public function test_audit_records_have_no_update_or_delete_routes(): void
    {
        $ctx = $this->context([Permissions::AuditView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id]);
        $this->put('/admin/audit/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
        $this->delete('/admin/audit/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }
}
