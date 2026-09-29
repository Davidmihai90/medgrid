<?php

namespace App\Http\Controllers\Api\Destination;

use App\Domain\Destination\Services\DestinationRuleService;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Destination\ActivateRuleVersionRequest;
use App\Http\Requests\Destination\StoreRuleSetRequest;
use App\Http\Requests\Destination\StoreRuleVersionRequest;
use App\Models\DestinationRuleSet;
use App\Models\DestinationRuleSetVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationRuleController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permissions::DestinationRulesView, $current->get()), 403);
        $sets = DestinationRuleSet::query()->where('organization_id', $current->get()->id)->with(['versions' => fn ($query) => $query->orderByDesc('version')])->orderBy('name')->get();

        return response()->json(['data' => $sets]);
    }

    public function store(StoreRuleSetRequest $request, CurrentOrganization $current): JsonResponse
    {
        $set = DestinationRuleSet::create(['organization_id' => $current->get()->id, ...$request->validated()]);

        return response()->json(['data' => $set], 201);
    }

    public function version(StoreRuleVersionRequest $request, DestinationRuleSet $ruleSet, CurrentOrganization $current, DestinationRuleService $service): JsonResponse
    {
        abort_unless($ruleSet->organization_id === $current->get()->id, 404);
        $version = $service->createDraft($ruleSet, $request->user(), $request->validated('definition'));

        return response()->json(['data' => $version], 201);
    }

    public function activate(ActivateRuleVersionRequest $request, DestinationRuleSetVersion $version, CurrentOrganization $current, DestinationRuleService $service): JsonResponse
    {
        abort_unless($version->organization_id === $current->get()->id, 404);

        return response()->json(['data' => $service->activate($version, $request->user())]);
    }
}
