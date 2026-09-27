<?php

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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'active']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([AssignCorrelationId::class, SecurityHeaders::class]);
        $middleware->alias(['active' => EnsureActiveAccount::class, 'current.organization' => EnsureCurrentOrganization::class, 'area' => EnsureApplicationAreaAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'You are not authorized to perform this action.']], 403);
            }
        });
    })->create();
