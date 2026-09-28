<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\ChangeAssignmentRequest;
use App\Models\CaseVehicleAssignment;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class AssignmentManagementController extends Controller
{
    public function destroy(ChangeAssignmentRequest $request, CaseVehicleAssignment $assignment, CurrentOrganization $current, DispatchService $dispatch): JsonResponse
    {
        abort_unless($assignment->organization_id === $current->get()?->id, 404);
        $this->authorize('cancel', $assignment);

        $assignment = $dispatch->cancelAssignment($assignment, $request->user(), $request->validated('reason'));

        return response()->json(['data' => ['id' => $assignment->id, 'status' => $assignment->status->value]]);
    }

    public function update(ChangeAssignmentRequest $request, CaseVehicleAssignment $assignment, CurrentOrganization $current, DispatchService $dispatch): JsonResponse
    {
        abort_unless($assignment->organization_id === $current->get()?->id, 404);
        $this->authorize('reassign', $assignment);

        $vehicle = Vehicle::forOrganization($current->get())->findOrFail($request->validated('vehicle_id'));
        $assignment = $dispatch->reassign(
            $assignment,
            $vehicle,
            $request->user(),
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        );

        return response()->json(['data' => ['id' => $assignment->id, 'status' => $assignment->status->value, 'vehicle_id' => $assignment->vehicle_id]]);
    }
}
