<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Requests\Dispatch\ChangeAssignmentRequest;
use App\Models\CaseVehicleAssignment;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

class DispatchAssignmentManagementController extends Controller
{
    public function destroy(ChangeAssignmentRequest $request, CaseVehicleAssignment $assignment, CurrentOrganization $current, DispatchService $dispatch): RedirectResponse
    {
        abort_unless($assignment->organization_id === $current->get()?->id, 404);
        $this->authorize('cancel', $assignment);

        $dispatch->cancelAssignment($assignment, $request->user(), $request->validated('reason'));

        return redirect()
            ->route('area.dispatch', ['case' => $assignment->emergency_case_id])
            ->with('status', 'Unit assignment cancelled. The case is ready for a new assignment.');
    }

    public function update(ChangeAssignmentRequest $request, CaseVehicleAssignment $assignment, CurrentOrganization $current, DispatchService $dispatch): RedirectResponse
    {
        abort_unless($assignment->organization_id === $current->get()?->id, 404);
        $this->authorize('reassign', $assignment);

        $vehicle = Vehicle::forOrganization($current->get())->findOrFail($request->validated('vehicle_id'));
        $newAssignment = $dispatch->reassign(
            $assignment,
            $vehicle,
            $request->user(),
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        );

        return redirect()
            ->route('area.dispatch', ['case' => $newAssignment->emergency_case_id])
            ->with('status', 'Unit reassigned to '.$vehicle->callsign.'.');
    }
}
