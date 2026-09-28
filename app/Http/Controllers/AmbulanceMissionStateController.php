<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\CaseVehicleAssignment;
use Illuminate\Http\RedirectResponse;

class AmbulanceMissionStateController extends Controller
{
    public function deliver(CaseVehicleAssignment $assignment, CurrentOrganization $c, DispatchService $s): RedirectResponse
    {
        abort_unless($assignment->organization_id === $c->get()?->id, 404);
        $this->authorize('deliver', $assignment);
        $s->deliver($assignment, request()->user());

        return back()->with('status', 'Mission delivery acknowledged.');
    }

    public function accept(CaseVehicleAssignment $assignment, CurrentOrganization $c, DispatchService $s): RedirectResponse
    {
        abort_unless($assignment->organization_id === $c->get()?->id, 404);
        $this->authorize('accept', $assignment);
        $s->accept($assignment, request()->user());

        return back()->with('status', 'Mission accepted.');
    }
}
