<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    public function test_guest_is_rejected_from_me_endpoint(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_user_receives_safe_identity_and_organization_context(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['user'])
            ->withSession(['current_organization_id' => $ctx['organization']->id])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $ctx['user']->id)
            ->assertJsonPath('data.current_organization.id', $ctx['organization']->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status', 'current_organization' => ['id', 'name', 'slug', 'timezone']]]);

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }
}
