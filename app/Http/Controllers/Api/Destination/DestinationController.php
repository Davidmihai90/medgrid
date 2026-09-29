<?php

namespace App\Http\Controllers\Api\Destination;

use App\Domain\Destination\Services\DestinationAccess;
use App\Domain\Destination\Services\DestinationEvaluationService;
use App\Domain\Destination\Services\DestinationRequirementService;
use App\Domain\Destination\Services\DestinationSelectionService;
use App\Domain\Identity\Support\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Destination\CancelRequirementRequest;
use App\Http\Requests\Destination\RunEvaluationRequest;
use App\Http\Requests\Destination\SelectDestinationRequest;
use App\Http\Requests\Destination\StoreRequirementRequest;
use App\Http\Resources\Destination\EvaluationResource;
use App\Http\Resources\Destination\RequirementResource;
use App\Http\Resources\Destination\SelectionResource;
use App\Models\DestinationRequirement;
use App\Models\DestinationRuleSetVersion;
use App\Models\Hospital;
use App\Models\PatientEncounter;
use Illuminate\Http\JsonResponse;

class DestinationController extends Controller
{
    public function show(PatientEncounter $encounter, DestinationAccess $access): JsonResponse
    {
        $access->ensureEncounter(request()->user(), $encounter, Permissions::DestinationEvaluationsView);
        $encounter->load([
            'destinationRequirements' => fn ($query) => $query->latest(),
            'destinationEvaluations' => fn ($query) => $query->with(['candidates.hospital', 'requirementSet.requirements', 'ruleVersion.ruleSet'])->latest()->limit(10),
            'destinationSelections' => fn ($query) => $query->with('hospital')->latest(),
        ]);

        return response()->json(['data' => [
            'encounter_id' => $encounter->id,
            'destination_version' => $encounter->destination_version,
            'requirements' => RequirementResource::collection($encounter->destinationRequirements),
            'evaluations' => EvaluationResource::collection($encounter->destinationEvaluations),
            'selections' => SelectionResource::collection($encounter->destinationSelections),
        ]]);
    }

    public function storeRequirement(StoreRequirementRequest $request, PatientEncounter $encounter, DestinationAccess $access, DestinationRequirementService $service): RequirementResource
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);

        return new RequirementResource($service->create($encounter, $request->user(), $request->validated()));
    }

    public function replaceRequirement(StoreRequirementRequest $request, PatientEncounter $encounter, DestinationRequirement $requirement, DestinationAccess $access, DestinationRequirementService $service): RequirementResource
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);
        abort_unless($requirement->patient_encounter_id === $encounter->id, 404);

        return new RequirementResource($service->replace($requirement, $request->user(), $request->validated()));
    }

    public function cancelRequirement(CancelRequirementRequest $request, PatientEncounter $encounter, DestinationRequirement $requirement, DestinationAccess $access, DestinationRequirementService $service): RequirementResource
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationRequirementsManage);
        abort_unless($requirement->patient_encounter_id === $encounter->id, 404);

        return new RequirementResource($service->cancel($requirement, $request->user(), $request->validated('reason')));
    }

    public function evaluate(RunEvaluationRequest $request, PatientEncounter $encounter, DestinationAccess $access, DestinationEvaluationService $service): EvaluationResource
    {
        $access->ensureEncounter($request->user(), $encounter, Permissions::DestinationEvaluationsCreate);
        $version = DestinationRuleSetVersion::findOrFail($request->validated('rule_version_id'));
        abort_unless($version->organization_id === $encounter->organization_id, 404);

        return new EvaluationResource($service->evaluate($encounter, $version, $request->user(), $request->validated('idempotency_key')));
    }

    public function select(SelectDestinationRequest $request, PatientEncounter $encounter, DestinationSelectionService $service): SelectionResource
    {
        $hospital = Hospital::findOrFail($request->validated('hospital_id'));

        return new SelectionResource($service->select($encounter, $hospital, $request->user(), $request->validated()));
    }
}
