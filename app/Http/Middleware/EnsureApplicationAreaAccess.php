<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Services\ApplicationAreaRegistry;
use App\Domain\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationAreaAccess
{
    public function __construct(private CurrentOrganization $current, private ApplicationAreaRegistry $areas) {}

    public function handle(Request $request, Closure $next, string $area): Response
    {
        $organization = $this->current->get();
        abort_unless($organization && $request->user() && $this->areas->canAccess($request->user(), $organization, $area), 403);

        return $next($request);
    }
}
