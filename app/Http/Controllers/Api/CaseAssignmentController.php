<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\AssignVehicleRequest;
use App\Models\EmergencyCase;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class CaseAssignmentController extends Controller
{
    public function store(AssignVehicleRequest $r, EmergencyCase $emergencyCase, CurrentOrganization $current, DispatchService $service): JsonResponse
    {
        abort_unless($emergencyCase->organization_id === $current->get()?->id, 404);
        $vehicle = Vehicle::forOrganization($current->get())->findOrFail($r->validated('vehicle_id'));
        $a = $service->assign($emergencyCase, $vehicle, $r->user(), $r->validated('idempotency_key'));

        return response()->json(['data' => ['id' => $a->id, 'status' => $a->status->value, 'case_id' => $a->emergency_case_id, 'vehicle_id' => $a->vehicle_id]], 201);
    }
}
