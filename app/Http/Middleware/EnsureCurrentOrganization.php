<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->current->get(), 403, 'No active organization is available.');

        return $next($request);
    }
}
