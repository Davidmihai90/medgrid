<?php

namespace Tests\Feature;

use App\Domain\Health\Services\SystemHealthService;
use App\Domain\Identity\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use CreatesAuthorizationContext,RefreshDatabase;

    public function test_public_liveness_leaks_no_infrastructure_details(): void
    {
        $this->getJson('/health/live')->assertExactJson(['status' => 'ok'])->assertDontSee('database')->assertDontSee('redis');
    }

    public function test_detailed_health_requires_authorization(): void
    {
        $ctx = $this->context([Permissions::OrganizationsView]);
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin/system/health')->assertForbidden();
    }

    public function test_authorized_user_can_view_detailed_health(): void
    {
        $ctx = $this->context([Permissions::SystemHealthView]);
        $this->mock(SystemHealthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('check')->once()->andReturn(['application' => ['status' => 'HEALTHY', 'details' => ['version' => 'test']]]);
        });
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->get('/admin/system/health')->assertOk()->assertSee('HEALTHY');
    }
}
