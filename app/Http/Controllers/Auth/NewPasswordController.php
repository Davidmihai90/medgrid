<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $passwordWasChanged = false;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($audit, &$passwordWasChanged): void {
                if (in_array($user->status, [UserStatus::Suspended, UserStatus::Disabled], true)) {
                    $audit->record(
                        'authentication.password_reset_denied',
                        $user,
                        metadata: ['account_status' => $user->status->value],
                        result: 'DENIED',
                    );

                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $passwordWasChanged = true;

                event(new PasswordReset($user));
                $audit->record('authentication.password_reset', $user, actor: $user);
            },
        );

        if ($status === Password::PASSWORD_RESET && ! $passwordWasChanged) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Password reset is unavailable for this account.',
            ]);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
