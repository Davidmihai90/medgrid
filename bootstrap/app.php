<?php

use App\Domain\Destination\Exceptions\DestinationConflict;
use App\Domain\Dispatch\Exceptions\DispatchConflict;
use App\Domain\Hospitals\Exceptions\HospitalStateConflict;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureApplicationAreaAccess;
use App\Http\Middleware\EnsureCurrentOrganization;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'active']])->withMiddleware(function (Middleware $m): void {
    $m->append([AssignCorrelationId::class, SecurityHeaders::class]);
    $m->alias(['active' => EnsureActiveAccount::class, 'current.organization' => EnsureCurrentOrganization::class, 'area' => EnsureApplicationAreaAccess::class]);
})->withExceptions(function (Exceptions $e): void {
    $e->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
    $e->render(function (DestinationConflict $x, Request $r) {
        return $r->is('api/*') ? response()->json(['error' => ['code' => 'DESTINATION_CONFLICT', 'message' => $x->getMessage()]], 409) : back()->withErrors(['destination' => $x->getMessage()])->withInput();
    });
    $e->render(function (DispatchConflict $x, Request $r) {
        return $r->is('api/*') ? response()->json(['error' => ['code' => 'DISPATCH_CONFLICT', 'message' => $x->getMessage()]], 409) : back()->withErrors(['dispatch' => $x->getMessage()])->withInput();
    });
    $e->render(function (HospitalStateConflict $x, Request $r) {
        return $r->is('api/*') ? response()->json(['error' => ['code' => 'HOSPITAL_STATE_CONFLICT', 'message' => $x->getMessage()]], 409) : back()->withErrors(['hospital' => $x->getMessage()])->withInput();
    });
    $e->render(function (AuthorizationException $x, Request $r) {
        if ($r->is('api/*')) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'You are not authorized to perform this action.']], 403);
        }
    });
})->create();
