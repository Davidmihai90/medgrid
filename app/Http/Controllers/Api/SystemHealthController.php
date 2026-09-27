<?php

namespace App\Http\Controllers\Api;

use App\Domain\Health\Services\SystemHealthService;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    public function __invoke(CurrentOrganization $current, SystemHealthService $health): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission(Permissions::SystemHealthView, $current->get()), 403);

        return response()->json(['data' => $health->check()]);
    }
}
