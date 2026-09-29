<?php

namespace App\Http\Controllers;

use App\Domain\Destination\Services\DestinationAccess;
use App\Domain\Destination\Services\DestinationEvaluationService;
use App\Domain\Destination\Services\DestinationRequirementService;
use App\Domain\Destination\Services\DestinationSelectionService;
use App\Domain\Identity\Support\Permissions;
use App\Http\Requests\Destination\CancelRequirementRequest;
use App\Http\Requests\Destination\RunEvaluationRequest;
use App\Http\Requests\Destination\SelectDestinationRequest;
use App\Http\Requests\Destination\StoreRequirementRequest;
use App\Models\DestinationRequirement;
use App\Models\DestinationRuleSetVersion;
use App\Models\Hospital;
use App\Models\PatientEncounter;
use Illuminate\Http\RedirectResponse;

class MedicalDestinationController extends Controller
{
    public function storeRequirement(StoreRequirementRequest $request, PatientEncounter $encounter, DestinationAccess $access, DestinationRequirementService $service): RedirectResponse
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);
        $service->create($encounter, $request->user(), $request->validated());

        return back()->with('status', 'Destination requirement recorded.');
    }

    public function replaceRequirement(StoreRequirementRequest $request, PatientEncounter $encounter, DestinationRequirement $requirement, DestinationAccess $access, DestinationRequirementService $service): RedirectResponse
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);
        abort_unless($requirement->patient_encounter_id === $encounter->id, 404);
        $service->replace($requirement, $request->user(), $request->validated());

        return back()->with('status', 'Destination requirement superseded.');
    }

    public function cancelRequirement(CancelRequirementRequest $request, PatientEncounter $encounter, DestinationRequirement $requirement, DestinationAccess $access, DestinationRequirementService $service): RedirectResponse
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);
        abort_unless($requirement->patient_encounter_id === $encounter->id, 404);
        $service->cancel($requirement, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Destination requirement cancelled.');
    }

    public function evaluate(RunEvaluationRequest $request, PatientEncounter $encounter, DestinationAccess $access, DestinationEvaluationService $service): RedirectResponse
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationEvaluationsCreate);
        $version = DestinationRuleSetVersion::findOrFail($request->validated('rule_version_id'));
        abort_unless($version->organization_id === $encounter->organization_id, 404);
        $service->evaluate($encounter, $version, $request->user(), $request->validated('idempotency_key'));

        return back()->with('status', 'Destination evaluation completed.');
    }

    public function select(SelectDestinationRequest $request, PatientEncounter $encounter, DestinationSelectionService $service): RedirectResponse
    {
        $hospital = Hospital::findOrFail($request->validated('hospital_id'));
        $service->select($encounter, $hospital, $request->user(), $request->validated());

        return back()->with('status', 'Human destination decision recorded and hospital notified.');
    }
}
