<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Requests\Dispatch\StoreEmergencyCaseRequest;
use App\Http\Requests\Dispatch\TriageCaseRequest;
use App\Models\EmergencyCase;
use Illuminate\Http\RedirectResponse;

class DispatchCaseController extends Controller
{
    public function store(StoreEmergencyCaseRequest $r, CurrentOrganization $c, DispatchService $s): RedirectResponse
    {
        $case = $s->createCase($c->get(), $r->user(), $r->validated());

        return redirect()->route('area.dispatch', ['case' => $case->id])->with('status', 'Emergency case created.');
    }

    public function triage(TriageCaseRequest $r, EmergencyCase $emergencyCase, CurrentOrganization $c, DispatchService $s): RedirectResponse
    {
        abort_unless($emergencyCase->organization_id === $c->get()?->id, 404);
        $s->triage($emergencyCase, $r->user(), EmergencyCasePriority::from($r->validated('priority')), $r->validated('dispatcher_notes'));

        return back()->with('status', 'Triage recorded.');
    }
}
