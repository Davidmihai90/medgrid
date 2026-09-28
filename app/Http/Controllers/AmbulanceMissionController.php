<?php

namespace App\Http\Controllers;

use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\CaseVehicleAssignment;
use Illuminate\View\View;

class AmbulanceMissionController extends Controller
{
    public function __invoke(CurrentOrganization $current): View
    {
        $org = $current->get();
        $missions = CaseVehicleAssignment::where('organization_id', $org->id)->active()->whereHas('vehicle.crewAssignments', fn ($q) => $q->where('user_id', request()->user()->id)->whereNull('ended_at'))->with(['emergencyCase', 'vehicle'])->latest('assigned_at')->get();

        return view('ambulance.missions', compact('missions'));
    }
}
