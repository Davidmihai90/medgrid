<?php

namespace App\Http\Controllers\Api;

use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Services\AmbulanceAccess;
use App\Domain\Clinical\Services\ClinicalService;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\AssessmentResponsesRequest;
use App\Http\Requests\Clinical\CorrectVitalRequest;
use App\Http\Requests\Clinical\StoreClinicalNoteRequest;
use App\Http\Requests\Clinical\StoreEncounterRequest;
use App\Http\Requests\Clinical\StoreVitalRequest;
use App\Http\Requests\Clinical\UpdateConditionRequest;
use App\Http\Requests\Clinical\UpdatePatientIdentityRequest;
use App\Http\Requests\Clinical\WorkflowTransitionRequest;
use App\Models\Assessment;
use App\Models\AssessmentTemplateVersion;
use App\Models\EmergencyCase;
use App\Models\Patient;
use App\Models\PatientEncounter;
use App\Models\VitalObservation;
use Illuminate\Http\JsonResponse;

class ClinicalController extends Controller
{
    private function own(object $model, CurrentOrganization $current): void
    {
        abort_unless($model->organization_id === $current->get()?->id, 404);
    }

    public function workflow(WorkflowTransitionRequest $request, EmergencyCase $case, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($case, $current);
        $case = $clinical->transition($case, $request->user(), $request->enum('status', EmergencyCaseStatus::class));

        return response()->json(['data' => ['id' => $case->id, 'status' => $case->status->value, 'version' => $case->version]]);
    }

    public function encounters(EmergencyCase $case, CurrentOrganization $current, AmbulanceAccess $access): JsonResponse
    {
        $this->own($case, $current);
        $access->activeAssignment($case, request()->user(), Permissions::EncountersView);

        return response()->json(['data' => $case->encounters()->with('patient')->get()->map(fn ($e) => $this->encounterData($e))]);
    }

    public function createEncounter(StoreEncounterRequest $request, EmergencyCase $case, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($case, $current);
        $encounter = $clinical->createEncounter($case, $request->user(), $request->validated());

        return response()->json(['data' => $this->encounterData($encounter->load('patient'))], 201);
    }

    public function encounter(PatientEncounter $encounter, CurrentOrganization $current, AmbulanceAccess $access): JsonResponse
    {
        $this->own($encounter, $current);
        $access->activeAssignment($encounter->emergencyCase, request()->user(), Permissions::EncountersView);
        $encounter->load(['patient', 'vitals', 'assessments.templateVersion.template', 'assessments.responses', 'notes']);

        return response()->json(['data' => $this->encounterData($encounter, true)]);
    }

    public function updatePatient(UpdatePatientIdentityRequest $request, PatientEncounter $encounter, Patient $patient, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($encounter, $current);
        $data = $request->validated();
        $version = (int) $data['entity_version'];
        unset($data['entity_version']);
        $patient = $clinical->updatePatientIdentity($patient, $encounter, $request->user(), $data, $version);

        return response()->json(['data' => ['id' => $patient->id, 'identity_status' => $patient->identity_status->value, 'first_name' => $patient->first_name, 'last_name' => $patient->last_name, 'version' => $patient->version]]);
    }

    public function vitals(PatientEncounter $encounter, CurrentOrganization $current, AmbulanceAccess $access): JsonResponse
    {
        $this->own($encounter, $current);
        $access->activeAssignment($encounter->emergencyCase, request()->user(), Permissions::VitalsView);

        return response()->json(['data' => $encounter->vitals()->get()]);
    }

    public function createVital(StoreVitalRequest $request, PatientEncounter $encounter, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($encounter, $current);

        return response()->json(['data' => $clinical->recordVital($encounter, $request->user(), $request->validated())], 201);
    }

    public function correctVital(CorrectVitalRequest $request, VitalObservation $vital, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($vital, $current);
        $data = $request->validated();
        $reason = $data['reason'];
        unset($data['reason']);

        return response()->json(['data' => $clinical->correctVital($vital, $request->user(), $data, $reason)], 201);
    }

    public function condition(UpdateConditionRequest $request, PatientEncounter $encounter, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($encounter, $current);
        $encounter = $clinical->updateCondition($encounter, $request->user(), ConditionLevel::from($request->validated('condition_level')), (int) $request->validated('entity_version'));

        return response()->json(['data' => ['id' => $encounter->id, 'condition_level' => $encounter->condition_level->value, 'version' => $encounter->version]]);
    }

    public function note(StoreClinicalNoteRequest $request, PatientEncounter $encounter, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($encounter, $current);
        $note = $clinical->addNote($encounter, $request->user(), $request->validated('body'), $request->validated('operation_id'));

        return response()->json(['data' => ['id' => $note->id, 'recorded_at' => $note->recorded_at]], 201);
    }

    public function startAssessment(PatientEncounter $encounter, AssessmentTemplateVersion $version, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($encounter, $current);

        return response()->json(['data' => $clinical->startAssessment($encounter, $version, request()->user())], 201);
    }

    public function responses(AssessmentResponsesRequest $request, Assessment $assessment, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($assessment, $current);

        return response()->json(['data' => $clinical->saveResponses($assessment, $request->user(), $request->validated('responses'))]);
    }

    public function complete(Assessment $assessment, CurrentOrganization $current, ClinicalService $clinical): JsonResponse
    {
        $this->own($assessment, $current);

        return response()->json(['data' => $clinical->completeAssessment($assessment, request()->user())]);
    }

    private function encounterData(PatientEncounter $encounter, bool $detailed = false): array
    {
        $data = ['id' => $encounter->id, 'case_id' => $encounter->emergency_case_id, 'encounter_number' => $encounter->encounter_number, 'status' => $encounter->status->value, 'condition_level' => $encounter->condition_level->value, 'version' => $encounter->version, 'patient' => $encounter->patient ? ['id' => $encounter->patient->id, 'identity_status' => $encounter->patient->identity_status->value, 'first_name' => $encounter->patient->first_name, 'last_name' => $encounter->patient->last_name, 'estimated_age_min' => $encounter->patient->estimated_age_min, 'estimated_age_max' => $encounter->patient->estimated_age_max, 'sex' => $encounter->patient->sex, 'version' => $encounter->patient->version] : null];
        if ($detailed) {
            $data += ['vitals' => $encounter->vitals, 'assessments' => $encounter->assessments, 'notes' => $encounter->notes];
        }

        return $data;
    }
}
