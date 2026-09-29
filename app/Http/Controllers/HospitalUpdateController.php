<?php

namespace App\Http\Controllers;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Hospitals\Services\HospitalOperationsService;
use App\Domain\Identity\Support\Permissions;
use App\Http\Requests\Hospital\StoreCapabilityAvailabilityRequest;
use App\Http\Requests\Hospital\StoreReceivingStatusRequest;
use App\Http\Requests\Hospital\StoreResourceStateRequest;
use App\Http\Requests\Hospital\StoreRestrictionRequest;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalCaseNotification;
use App\Models\HospitalResourceDefinition;
use Illuminate\Http\RedirectResponse;

class HospitalUpdateController extends Controller
{
    public function receiving(StoreReceivingStatusRequest $request, Hospital $hospital, HospitalOperationsService $operations): RedirectResponse
    {
        $operations->updateReceiving($hospital, $request->user(), $request->validated());

        return back()->with('status', 'Receiving status recorded.');
    }

    public function capability(StoreCapabilityAvailabilityRequest $request, HospitalCapability $capability, HospitalAccess $access, HospitalOperationsService $operations): RedirectResponse
    {
        $access->ensure($request->user(), $capability->hospital, Permissions::HospitalAvailabilityUpdate);
        $operations->updateCapabilityAvailability($capability, $request->user(), $request->validated());

        return back()->with('status', 'Capability availability recorded.');
    }

    public function resource(StoreResourceStateRequest $request, Hospital $hospital, HospitalResourceDefinition $resource, HospitalOperationsService $operations): RedirectResponse
    {
        $operations->updateResource($hospital, $resource, $request->user(), $request->validated());

        return back()->with('status', 'Resource state recorded.');
    }

    public function restriction(StoreRestrictionRequest $request, Hospital $hospital, HospitalOperationsService $operations): RedirectResponse
    {
        $operations->createRestriction($hospital, $request->user(), $request->validated());

        return back()->with('status', 'Operational restriction recorded.');
    }

    public function acknowledge(HospitalCaseNotification $notification, HospitalAccess $access, HospitalOperationsService $operations): RedirectResponse
    {
        $access->ensure(request()->user(), $notification->hospital, Permissions::HospitalIncomingAcknowledge);
        $operations->acknowledge($notification, request()->user());

        return back()->with('status', 'Incoming notification acknowledged. This is not clinical acceptance.');
    }
}
