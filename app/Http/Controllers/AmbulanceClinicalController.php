<?php

namespace App\Http\Controllers;

use App\Domain\Clinical\Enums\ConditionLevel;
use App\Domain\Clinical\Services\ClinicalService;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Requests\Clinical\AssessmentResponsesRequest;
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
use Illuminate\Http\RedirectResponse;

class AmbulanceClinicalController extends Controller
{
    private function own(object $m, CurrentOrganization $c): void
    {
        abort_unless($m->organization_id === $c->get()?->id, 404);
    }

    private function back(PatientEncounter $e, string $message): RedirectResponse
    {
        return redirect()->route('area.ambulance', ['mission' => $e->emergencyCase->assignments()->active()->value('id'), 'encounter' => $e->id])->with('status', $message);
    }

    public function workflow(WorkflowTransitionRequest $r, EmergencyCase $case, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($case, $c);
        $s->transition($case, $r->user(), $r->enum('status', EmergencyCaseStatus::class));

        return back()->with('status', 'Operational state updated.');
    }

    public function encounter(StoreEncounterRequest $r, EmergencyCase $case, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($case, $c);

        return $this->back($s->createEncounter($case, $r->user(), $r->validated()), 'Patient encounter created.');
    }

    public function patient(UpdatePatientIdentityRequest $request, PatientEncounter $encounter, Patient $patient, CurrentOrganization $current, ClinicalService $clinical): RedirectResponse
    {
        $this->own($encounter, $current);
        $data = $request->validated();
        $version = (int) $data['entity_version'];
        unset($data['entity_version']);
        $clinical->updatePatientIdentity($patient, $encounter, $request->user(), $data, $version);

        return $this->back($encounter, 'Patient identity updated.');
    }

    public function vital(StoreVitalRequest $r, PatientEncounter $encounter, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($encounter, $c);
        $s->recordVital($encounter, $r->user(), $r->validated());

        return $this->back($encounter, 'Vital observation saved to MEDGRID.');
    }

    public function condition(UpdateConditionRequest $r, PatientEncounter $encounter, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($encounter, $c);
        $s->updateCondition($encounter, $r->user(), ConditionLevel::from($r->validated('condition_level')), (int) $r->validated('entity_version'));

        return $this->back($encounter, 'Condition updated.');
    }

    public function note(StoreClinicalNoteRequest $r, PatientEncounter $encounter, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($encounter, $c);
        $s->addNote($encounter, $r->user(), $r->validated('body'), $r->validated('operation_id'));

        return $this->back($encounter, 'Clinical note saved.');
    }

    public function start(PatientEncounter $encounter, AssessmentTemplateVersion $version, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($encounter, $c);
        $a = $s->startAssessment($encounter, $version, request()->user());

        return redirect()->route('area.ambulance', ['mission' => $encounter->emergencyCase->assignments()->active()->value('id'), 'encounter' => $encounter->id, 'assessment' => $a->id])->with('status', 'Assessment started.');
    }

    public function responses(AssessmentResponsesRequest $r, Assessment $assessment, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($assessment, $c);
        $s->saveResponses($assessment, $r->user(), $r->validated('responses'));

        return back()->with('status', 'Assessment responses saved.');
    }

    public function complete(Assessment $assessment, CurrentOrganization $c, ClinicalService $s): RedirectResponse
    {
        $this->own($assessment, $c);
        $s->completeAssessment($assessment, request()->user());

        return back()->with('status', 'Assessment completed.');
    }
}
