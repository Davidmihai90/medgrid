<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $current): JsonResponse
    {
        $org = $current->get();

        return response()->json(['data' => ['id' => $request->user()->id, 'name' => $request->user()->name, 'email' => $request->user()->email, 'status' => $request->user()->status->value, 'current_organization' => $org?->only(['id', 'name', 'slug', 'timezone'])]]);
    }
}
