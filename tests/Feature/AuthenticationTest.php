<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreatesAuthorizationContext;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesAuthorizationContext, RefreshDatabase;

    public function test_guest_is_denied_protected_application_access(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_authenticate(): void
    {
        $this->context();
        $this->post('/login', ['email' => User::first()->email, 'password' => 'Test-Only!Password2026'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_suspended_user_is_denied_operational_access(): void
    {
        $user = User::factory()->suspended()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-Only!Password2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_user_is_denied_operational_access(): void
    {
        $user = User::factory()->disabled()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-Only!Password2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_terminates_the_session(): void
    {
        $ctx = $this->context();
        $this->actingAs($ctx['user'])->withSession(['current_organization_id' => $ctx['organization']->id])->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_user_can_reset_password(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Updated!Password2026',
            'password_confirmation' => 'Updated!Password2026',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('Updated!Password2026', $user->refresh()->password));
    }

    public function test_disabled_user_cannot_reset_password(): void
    {
        $user = User::factory()->disabled()->create();
        $originalPassword = $user->password;
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Updated!Password2026',
            'password_confirmation' => 'Updated!Password2026',
        ])->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->refresh()->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'authentication.password_reset_denied', 'result' => 'DENIED']);
    }
}
