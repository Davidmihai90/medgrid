<?php

namespace Tests\Feature;

use App\Events\OrganizationOperationalNotice;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class RealtimeAuthorizationTest extends TestCase
{
    use CreatesAuthorizationContext,RefreshDatabase;

    public function test_unauthenticated_user_cannot_authorize_private_channel(): void
    {
        $org = Organization::factory()->create();
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-organization.'.$org->id])->assertUnauthorized();
    }

    public function test_user_cannot_authorize_another_organization_channel(): void
    {
        $ctx = $this->context();
        $other = Organization::factory()->create();
        $this->actingAs($ctx['user'])->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-organization.'.$other->id])->assertForbidden();
    }

    public function test_authorized_user_can_authorize_own_organization_channel(): void
    {
        $ctx = $this->context();
        $this->actingAs($ctx['user'])->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-organization.'.$ctx['organization']->id])->assertOk();
    }

    public function test_operational_notice_uses_minimal_versioned_payload(): void
    {
        $ctx = $this->context();
        $event = new OrganizationOperationalNotice($ctx['organization']->id, 'Synthetic operational notice', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $payload = $event->broadcastWith();
        $this->assertSame('organization.operational.notice', $payload['event']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame(['message' => 'Synthetic operational notice'], $payload['data']);
        $this->assertSame('private-organization.'.$ctx['organization']->id, $event->broadcastOn()[0]->name);
    }
}
