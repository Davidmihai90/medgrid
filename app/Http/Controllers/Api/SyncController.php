<?php

namespace App\Http\Controllers\Api;

use App\Domain\Clinical\Services\SyncService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\SyncOperationsRequest;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __invoke(SyncOperationsRequest $r, CurrentOrganization $c, SyncService $s): JsonResponse
    {
        return response()->json(['data' => $s->process($r->validated('operations'), $r->user(), $c->get()->id, $r->hasSession() ? $r->session()->getId() : null)]);
    }
}
