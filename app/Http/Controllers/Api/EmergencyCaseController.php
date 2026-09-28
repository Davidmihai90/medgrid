<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dispatch\Services\DispatchService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\StoreEmergencyCaseRequest;
use App\Http\Resources\EmergencyCaseResource;
use App\Models\EmergencyCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmergencyCaseController extends Controller
{
    public function index(CurrentOrganization $current): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EmergencyCase::class);

        return EmergencyCaseResource::collection(EmergencyCase::forOrganization($current->get())->with('assignments')->latest('received_at')->paginate(25));
    }

    public function store(StoreEmergencyCaseRequest $r, CurrentOrganization $current, DispatchService $service): JsonResponse
    {
        $case = $service->createCase($current->get(), $r->user(), $r->validated());

        return (new EmergencyCaseResource($case))->response()->setStatusCode(201);
    }

    public function show(EmergencyCase $emergencyCase, CurrentOrganization $current): EmergencyCaseResource
    {
        abort_unless($emergencyCase->organization_id === $current->get()?->id, 404);
        $this->authorize('view', $emergencyCase);

        return new EmergencyCaseResource($emergencyCase->load('assignments'));
    }
}
