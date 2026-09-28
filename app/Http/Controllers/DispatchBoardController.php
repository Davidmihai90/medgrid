<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\VehicleStatus;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\EmergencyCase;
use App\Models\Vehicle;
use Illuminate\View\View;

class DispatchBoardController extends Controller
{
    public function __invoke(CurrentOrganization $current): View
    {
        $this->authorize('viewAny', EmergencyCase::class);

        $organization = $current->get();
        $cases = EmergencyCase::forOrganization($organization)
            ->with(['assignments.vehicle'])
            ->whereNotIn('status', ['CANCELLED', 'CLOSED'])
            ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 WHEN 'P5' THEN 5 ELSE 6 END")
            ->latest('received_at')
            ->get();
        $selected = request('case') ? $cases->firstWhere('id', request('case')) : $cases->first();
        $selected?->load('events', 'assignments.vehicle');
        $vehicles = Vehicle::forOrganization($organization)
            ->with(['crewAssignments' => fn ($query) => $query->whereNull('ended_at')->with('user')])
            ->orderBy('callsign')
            ->get();
        $activeAssignment = $selected?->assignments->first(fn ($assignment) => $assignment->status->isActive());
        $replacementVehicles = $vehicles
            ->where('is_active', true)
            ->where('status', VehicleStatus::Available)
            ->values();

        return view('dispatch.board', [
            'cases' => $cases,
            'selected' => $selected,
            'vehicles' => $vehicles,
            'priorities' => EmergencyCasePriority::cases(),
            'available' => VehicleStatus::Available,
            'activeAssignment' => $activeAssignment,
            'replacementVehicles' => $replacementVehicles,
        ]);
    }
}
