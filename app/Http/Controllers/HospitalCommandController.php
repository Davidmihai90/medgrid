<?php

namespace App\Http\Controllers;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Domain\Hospitals\Services\HospitalOperationalSnapshotService;
use App\Domain\Identity\Support\Permissions;
use App\Models\HospitalResourceDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HospitalCommandController extends Controller
{
    public function __invoke(Request $request, HospitalAccess $access, HospitalOperationalSnapshotService $snapshots): View
    {
        $hospitals = $access->accessibleQuery($request->user(), Permissions::HospitalsView)->orderBy('name')->get();
        $hospital = $request->filled('hospital') ? $hospitals->firstWhere('id', $request->string('hospital')->toString()) : $hospitals->first();
        abort_if($request->filled('hospital') && ! $hospital, 404);

        if (! $hospital) {
            return view('hospital.command', ['hospitals' => $hospitals, 'hospital' => null, 'snapshot' => null, 'incoming' => collect(), 'history' => collect(), 'resourceDefinitions' => collect()]);
        }

        $snapshot = $snapshots->for($hospital);
        $incoming = $hospital->notifications()
            ->with(['emergencyCase' => fn ($query) => $query->withCount('encounters'), 'encounter'])
            ->latest('notified_at')->limit(20)->get();
        $history = collect()
            ->merge($hospital->receivingStatuses()->latest('effective_at')->limit(20)->get()->map(fn ($row) => ['at' => $row->effective_at, 'label' => 'Receiving '.$row->status->value, 'detail' => $row->reason_text]))
            ->merge($hospital->capabilities()->with('definition')->get()->flatMap(fn ($capability) => $capability->availabilities()->latest('effective_at')->limit(10)->get()->map(fn ($row) => ['at' => $row->effective_at, 'label' => $capability->definition->name.' '.$row->status->value, 'detail' => $row->reason_text])))
            ->merge($hospital->resourceStates()->with('definition')->latest('effective_at')->limit(20)->get()->map(fn ($row) => ['at' => $row->effective_at, 'label' => $row->definition->name.' '.$row->status->value, 'detail' => $row->notes]))
            ->sortByDesc('at')->take(30)->values();

        return view('hospital.command', [
            'hospitals' => $hospitals,
            'hospital' => $hospital,
            'snapshot' => $snapshot,
            'incoming' => $incoming,
            'history' => $history,
            'resourceDefinitions' => HospitalResourceDefinition::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
