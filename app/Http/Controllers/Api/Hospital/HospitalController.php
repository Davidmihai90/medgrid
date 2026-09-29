<?php

namespace App\Http\Controllers\Api\Hospital;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Hospitals\Services\HospitalOperationalSnapshotService;
use App\Domain\Identity\Support\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hospital\HospitalResource;
use App\Http\Resources\Hospital\IncomingNotificationResource;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HospitalController extends Controller
{
    public function index(HospitalAccess $access): AnonymousResourceCollection
    {
        return HospitalResource::collection($access->accessibleQuery(request()->user(), Permissions::HospitalsView)->orderBy('name')->get());
    }

    public function show(Hospital $hospital, HospitalAccess $access): HospitalResource
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalsView);

        return new HospitalResource($hospital);
    }

    public function snapshot(Hospital $hospital, HospitalAccess $access, HospitalOperationalSnapshotService $snapshots): JsonResponse
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalsView);

        return response()->json(['data' => $snapshots->for($hospital)]);
    }

    public function departments(Hospital $hospital, HospitalAccess $access): JsonResponse
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalDepartmentsView);

        return response()->json(['data' => $hospital->departments()->orderBy('name')->get(['id', 'code', 'name', 'description', 'active'])]);
    }

    public function capabilities(Hospital $hospital, HospitalAccess $access): JsonResponse
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalCapabilitiesView);

        return response()->json(['data' => $hospital->capabilities()->with(['definition:id,code,name', 'department:id,name'])->orderBy('created_at')->get()]);
    }

    public function availability(Hospital $hospital, HospitalAccess $access): JsonResponse
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalAvailabilityView);

        return response()->json(['data' => [
            'receiving' => $hospital->receivingStatuses()->latest('effective_at')->limit(50)->get(),
            'capabilities' => $hospital->capabilities()->with(['definition:id,code,name', 'availabilities' => fn ($query) => $query->latest('effective_at')->limit(25)])->get(),
            'restrictions' => $hospital->restrictions()->latest('effective_at')->limit(50)->get(),
        ]]);
    }

    public function resources(Hospital $hospital, HospitalAccess $access): JsonResponse
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalResourcesView);

        return response()->json(['data' => $hospital->resourceStates()->with('definition:id,code,name')->latest('effective_at')->limit(100)->get()]);
    }

    public function incoming(Hospital $hospital, HospitalAccess $access): AnonymousResourceCollection
    {
        $access->ensure(request()->user(), $hospital, Permissions::HospitalIncomingView);
        $incoming = $hospital->notifications()->with(['emergencyCase' => fn ($query) => $query->withCount('encounters'), 'encounter'])->latest('notified_at')->paginate(25);

        return IncomingNotificationResource::collection($incoming);
    }
}
