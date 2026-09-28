<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\TriageCaseRequest;
use App\Http\Resources\EmergencyCaseResource;
use App\Models\EmergencyCase;

class CaseTriageController extends Controller
{
    public function store(TriageCaseRequest $r, EmergencyCase $emergencyCase, CurrentOrganization $current, DispatchService $service): EmergencyCaseResource
    {
        abort_unless($emergencyCase->organization_id === $current->get()?->id, 404);

        return new EmergencyCaseResource($service->triage($emergencyCase, $r->user(), EmergencyCasePriority::from($r->validated('priority')), $r->validated('dispatcher_notes')));
    }
}
