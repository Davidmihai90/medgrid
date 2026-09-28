<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Requests\Dispatch\AssignVehicleRequest;
use App\Models\EmergencyCase;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

class DispatchAssignmentController extends Controller
{
    public function store(AssignVehicleRequest $r, EmergencyCase $emergencyCase, CurrentOrganization $c, DispatchService $s): RedirectResponse
    {
        abort_unless($emergencyCase->organization_id === $c->get()?->id, 404);
        $v = Vehicle::forOrganization($c->get())->findOrFail($r->validated('vehicle_id'));
        $s->assign($emergencyCase, $v, $r->user(), $r->validated('idempotency_key'));

        return back()->with('status', 'Unit assigned; delivery is pending crew acknowledgement.');
    }
}
