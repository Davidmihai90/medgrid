<?php

namespace App\Http\Controllers;

use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\AssessmentTemplateVersion;
use App\Models\CaseVehicleAssignment;
use Illuminate\View\View;

class AmbulanceMissionController extends Controller
{
    public function __invoke(CurrentOrganization $current): View
    {
        $organization = $current->get();
        $missions = CaseVehicleAssignment::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->whereHas('vehicle.crewAssignments', fn ($query) => $query
                ->where('user_id', request()->user()->id)
                ->whereNull('ended_at'))
            ->with([
                'emergencyCase.encounters.patient',
                'emergencyCase.encounters.assessments:id,patient_encounter_id,status',
                'vehicle',
            ])
            ->latest('assigned_at')
            ->get();

        $mission = request('mission')
            ? $missions->firstWhere('id', request('mission'))
            : $missions->first();
        $encounter = $mission?->emergencyCase->encounters->firstWhere('id', request('encounter'))
            ?? $mission?->emergencyCase->encounters->first();

        if ($encounter) {
            $encounter->load([
                'patient',
                'vitals' => fn ($query) => $query->limit(100),
                'assessments.templateVersion.template',
                'assessments.responses',
                'notes' => fn ($query) => $query->limit(50),
            ]);
        }

        $assessment = $encounter?->assessments->firstWhere('id', request('assessment'))
            ?? $encounter?->assessments->first(fn ($item) => $item->status->value === 'IN_PROGRESS');
        $templates = AssessmentTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query
                ->where('organization_id', $organization->id)
                ->where('is_active', true))
            ->with('template')
            ->orderByDesc('version')
            ->get()
            ->unique('assessment_template_id')
            ->values();
        $latestVitals = $encounter?->vitals
            ->whereNull('superseded_at')
            ->sortByDesc('measured_at')
            ->unique(fn ($vital) => $vital->type->value)
            ?? collect();

        return view('ambulance.workspace', compact(
            'missions',
            'mission',
            'encounter',
            'assessment',
            'templates',
            'latestVitals',
        ));
    }
}
