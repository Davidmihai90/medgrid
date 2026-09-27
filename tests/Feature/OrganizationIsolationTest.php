<?php

namespace Tests\Feature;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use CreatesAuthorizationContext,RefreshDatabase;

    public function test_user_can_access_own_organization(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get(route('admin.organizations.show', $ctx['organization']))->assertOk();
    }

    public function test_user_cannot_access_unrelated_organization(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView]);
        $other = Organization::factory()->create();
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get(route('admin.organizations.show', $other))->assertForbidden();
    }

    public function test_unauthorized_organization_switch_is_rejected(): void
    {
        $ctx = $this->context();
        $other = Organization::factory()->create();
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->put(route('organization-context.update', $other))->assertForbidden();
    }

    public function test_inactive_membership_prevents_normal_access(): void
    {
        $ctx = $this->context([], [], MembershipStatus::Suspended);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/dashboard')->assertForbidden();
    }

    public function test_user_listing_does_not_expose_another_organization(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView, Permissions::UsersView]);
        $other = Organization::factory()->create();
        $outsider = User::factory()->create();
        OrganizationMembership::factory()->create(['organization_id' => $other->id, 'user_id' => $outsider->id]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get(route('admin.users.index'))->assertOk()->assertDontSee($outsider->email);
    }
}
