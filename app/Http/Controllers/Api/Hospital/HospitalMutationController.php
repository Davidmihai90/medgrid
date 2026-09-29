<?php

namespace App\Http\Controllers\Api\Hospital;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Hospitals\Services\HospitalOperationsService;
use App\Domain\Identity\Support\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hospital\StoreCapabilityAvailabilityRequest;
use App\Http\Requests\Hospital\StoreReceivingStatusRequest;
use App\Http\Requests\Hospital\StoreResourceStateRequest;
use App\Http\Requests\Hospital\StoreRestrictionRequest;
use App\Http\Resources\Hospital\IncomingNotificationResource;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalResourceDefinition;
use Illuminate\Http\JsonResponse;

class HospitalMutationController extends Controller
{
    public function receiving(StoreReceivingStatusRequest $request, Hospital $hospital, HospitalOperationsService $operations): JsonResponse
    {
        $report = $operations->updateReceiving($hospital, $request->user(), $request->validated());

        return response()->json(['data' => $report, 'meta' => ['hospital_version' => $hospital->fresh()->version]], 201);
    }

    public function capability(StoreCapabilityAvailabilityRequest $request, HospitalCapability $capability, HospitalAccess $access, HospitalOperationsService $operations): JsonResponse
    {
        $access->ensure($request->user(), $capability->hospital, Permissions::HospitalAvailabilityUpdate);
        $report = $operations->updateCapabilityAvailability($capability, $request->user(), $request->validated());

        return response()->json(['data' => $report, 'meta' => ['hospital_version' => $capability->hospital->fresh()->version]], 201);
    }

    public function resource(StoreResourceStateRequest $request, Hospital $hospital, HospitalResourceDefinition $resource, HospitalOperationsService $operations): JsonResponse
    {
        $report = $operations->updateResource($hospital, $resource, $request->user(), $request->validated());

        return response()->json(['data' => $report, 'meta' => ['hospital_version' => $hospital->fresh()->version]], 201);
    }

    public function restriction(StoreRestrictionRequest $request, Hospital $hospital, HospitalOperationsService $operations): JsonResponse
    {
        $restriction = $operations->createRestriction($hospital, $request->user(), $request->validated());

        return response()->json(['data' => $restriction, 'meta' => ['hospital_version' => $hospital->fresh()->version]], 201);
    }

    public function acknowledge(HospitalCaseNotification $notification, HospitalAccess $access, HospitalOperationsService $operations): IncomingNotificationResource
    {
        $access->ensure(request()->user(), $notification->hospital, Permissions::HospitalIncomingAcknowledge);
        $notification = $operations->acknowledge($notification, request()->user());

        return new IncomingNotificationResource($notification->load(['emergencyCase' => fn ($query) => $query->withCount('encounters'), 'encounter']));
    }
}
