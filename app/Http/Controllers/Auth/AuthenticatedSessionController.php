<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditRecorder $audit, CurrentOrganization $current): RedirectResponse
    {
        $key = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many login attempts. Try again later.']);
        }
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            $audit->record('authentication.login_failed', metadata: ['email_hash' => hash('sha256', Str::lower($request->string('email')))], result: 'DENIED');
            throw ValidationException::withMessages(['email' => 'The provided credentials are invalid.']);
        }
        $request->session()->regenerate();
        /** @var User $user */ $user = $request->user();
        if (! $user->isActive()) {
            $audit->record('authentication.login_denied', $user, metadata: ['account_status' => $user->status->value], result: 'DENIED', actor: $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'This account is not active.']);
        }
        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now()])->save();
        $current->clear();
        $audit->record('authentication.login_succeeded', $user, actor: $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $audit->record('authentication.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
