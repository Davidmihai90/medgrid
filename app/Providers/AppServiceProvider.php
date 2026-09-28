<?php

namespace App\Providers;

use App\Domain\Identity\Services\ApplicationAreaRegistry;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\CaseVehicleAssignment;
use App\Models\EmergencyCase;
use App\Models\Organization;
use App\Models\User;
use App\Policies\CaseVehicleAssignmentPolicy;
use App\Policies\EmergencyCasePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use App\Support\CorrelationContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CorrelationContext::class);
        $this->app->scoped(CurrentOrganization::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
        ResetPassword::createUrlUsing(fn (object $notifiable, string $token) => url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)));
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(EmergencyCase::class, EmergencyCasePolicy::class);
        Gate::policy(CaseVehicleAssignment::class, CaseVehicleAssignmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::before(fn (User $user) => $user->isPlatformSuperAdministrator() ? true : null);
        foreach (Permissions::All as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission, app(CurrentOrganization::class)->get()));
        }
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('medgrid.rate_limits.api', 120))->by($request->user()?->id ?: $request->ip()));
        View::composer('*', function ($view) {
            if (! auth()->check()) {
                return;
            } $current = app(CurrentOrganization::class);
            $org = $current->get();
            $view->with(['currentOrganization' => $org, 'availableOrganizations' => $current->availableFor(auth()->user()), 'navigationAreas' => app(ApplicationAreaRegistry::class)->allowed(auth()->user(), $org)]);
        });
    }
}
