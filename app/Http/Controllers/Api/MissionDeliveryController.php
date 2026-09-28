<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CaseVehicleAssignment;
use Illuminate\Http\JsonResponse;

class MissionDeliveryController extends Controller
{
    public function store(CaseVehicleAssignment $assignment, CurrentOrganization $current, DispatchService $service): JsonResponse
    {
        abort_unless($assignment->organization_id === $current->get()?->id, 404);
        $this->authorize('deliver', $assignment);
        $a = $service->deliver($assignment, request()->user());

        return response()->json(['data' => ['id' => $a->id, 'status' => $a->status->value]]);
    }
}
