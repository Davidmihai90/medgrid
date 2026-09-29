<?php

namespace App\Http\Controllers;

use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use App\Domain\Destination\Services\DestinationAccess;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\DestinationRuleSetVersion;
use App\Models\EmergencyCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MedicalCoordinatorController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $current, DestinationAccess $access): View
    {
        $organization = $current->get();
        abort_unless($request->user()->hasPermission(Permissions::DestinationRequirementsView, $organization), 403);

        $cases = EmergencyCase::forOrganization($organization)
            ->whereNotIn('status', ['CANCELLED', 'CLOSED'])
            ->whereHas('encounters')
            ->with(['encounters' => fn ($query) => $query
                ->with(['patient', 'destinationRequirements' => fn ($requirements) => $requirements->latest(), 'destinationSelections' => fn ($selections) => $selections->with('hospital')->latest()])
                ->orderBy('encounter_index')])
            ->latest('received_at')
            ->limit(100)
            ->get();

        $case = $request->filled('case') ? $cases->firstWhere('id', $request->string('case')->toString()) : $cases->first();
        abort_if($request->filled('case') && ! $case, 404);
        $encounter = $case?->encounters->firstWhere('id', $request->string('encounter')->toString()) ?? $case?->encounters->first();

        if ($encounter) {
            $encounter->load([
                'destinationEvaluations' => fn ($query) => $query->with(['candidates.hospital', 'requirementSet.requirements', 'ruleVersion.ruleSet'])->latest()->limit(10),
                'destinationSelections' => fn ($query) => $query->with(['hospital', 'candidate'])->latest(),
            ]);
        }

        $ruleVersions = DestinationRuleSetVersion::query()
            ->where('organization_id', $organization->id)
            ->where('status', DestinationRuleVersionStatus::Active)
            ->with('ruleSet')
            ->orderByDesc('activated_at')
            ->get();
        $hospitals = $access->hospitalQuery($request->user())->orderBy('name')->get();

        return view('medical.workspace', compact('cases', 'case', 'encounter', 'ruleVersions', 'hospitals'));
    }
}
