<?php

namespace App\Domain\Hospitals\Services;

use App\Domain\Hospitals\Enums\FreshnessState;
use App\Models\Hospital;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class HospitalOperationalSnapshotService
{
    public function for(Hospital $hospital, ?CarbonImmutable $referenceTime = null): array
    {
        $at = $referenceTime ?? CarbonImmutable::now('UTC');
        $hospital->loadMissing(['capabilities.definition', 'capabilities.department', 'capabilities.availabilities', 'receivingStatuses', 'resourceStates.definition', 'restrictions']);

        $receiving = $hospital->receivingStatuses
            ->filter(fn ($row) => $row->effective_at->lte($at))
            ->sortByDesc(fn ($row) => $row->effective_at->format('U.u').'|'.$row->id)
            ->first();

        $capabilities = $hospital->capabilities
            ->sortBy(fn ($capability) => $capability->definition->name)
            ->values()
            ->map(function ($capability) use ($at) {
                $report = $capability->availabilities
                    ->filter(fn ($row) => $row->effective_at->lte($at))
                    ->sortByDesc(fn ($row) => $row->effective_at->format('U.u').'|'.$row->id)
                    ->first();

                return [
                    'id' => $capability->id,
                    'code' => $capability->definition->code,
                    'name' => $capability->definition->name,
                    'department' => $capability->department?->name,
                    'enabled' => $capability->enabled,
                    'availability' => $this->project($report, $at, 'status'),
                ];
            });

        $resources = $hospital->resourceStates
            ->filter(fn ($row) => $row->effective_at->lte($at))
            ->groupBy('hospital_resource_definition_id')
            ->map(function ($states) use ($at) {
                $report = $states->sortByDesc(fn ($row) => $row->effective_at->format('U.u').'|'.$row->id)->first();
                $projected = $this->project($report, $at, 'status');

                return [
                    'definition_id' => $report->hospital_resource_definition_id,
                    'code' => $report->definition->code,
                    'name' => $report->definition->name,
                    'status' => $projected['value'],
                    'freshness' => $projected['freshness'],
                    'last_confirmed_at' => $projected['last_confirmed_at'],
                    'expires_at' => $projected['expires_at'],
                    'expired' => $projected['expired'],
                    'total_capacity' => $projected['expired'] ? null : $report->total_capacity,
                    'available_capacity' => $projected['expired'] ? null : $report->available_capacity,
                    'notes' => $report->notes,
                ];
            })->sortBy('name')->values();

        $restrictions = $hospital->restrictions
            ->filter(fn ($row) => $row->effective_at->lte($at) && ! $row->cancelled_at && (! $row->expires_at || $row->expires_at->gt($at)))
            ->sortByDesc('effective_at')->values()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description,
                'reason_code' => $row->reason_code->value,
                'effective_at' => $row->effective_at->toIso8601String(),
                'expires_at' => $row->expires_at?->toIso8601String(),
            ]);

        $lastUpdated = collect([$receiving?->created_at])
            ->merge($hospital->capabilities->flatMap->availabilities->pluck('created_at'))
            ->merge($hospital->resourceStates->pluck('created_at'))
            ->merge($hospital->restrictions->pluck('created_at'))
            ->filter()->max();

        return [
            'reference_time' => $at->toIso8601String(),
            'hospital' => [
                'id' => $hospital->id,
                'code' => $hospital->code,
                'name' => $hospital->name,
                'status' => $hospital->status->value,
                'version' => $hospital->version,
                'timezone' => $hospital->timezone,
            ],
            'receiving' => $this->project($receiving, $at, 'status'),
            'capabilities' => $capabilities->all(),
            'resources' => $resources->all(),
            'restrictions' => $restrictions->all(),
            'last_updated_at' => $lastUpdated?->toIso8601String(),
        ];
    }

    private function project(?Model $report, CarbonImmutable $at, string $field): array
    {
        if (! $report) {
            return ['value' => 'UNKNOWN', 'freshness' => FreshnessState::Unknown->value, 'last_confirmed_at' => null, 'expires_at' => null, 'expired' => false, 'reason_code' => null, 'reason_text' => null];
        }

        $expired = $report->expires_at?->lte($at) ?? false;
        $age = $report->effective_at->diffInMinutes($at);
        $fresh = (int) config('medgrid.hospital_freshness.fresh_minutes', 15);
        $aging = (int) config('medgrid.hospital_freshness.aging_minutes', 45);
        $freshness = $expired ? FreshnessState::Unknown : match (true) {
            $age <= $fresh => FreshnessState::Fresh,
            $age <= $aging => FreshnessState::Aging,
            default => FreshnessState::Stale,
        };

        return [
            'value' => $expired ? 'UNKNOWN' : $report->{$field}->value,
            'freshness' => $freshness->value,
            'last_confirmed_at' => $report->effective_at->toIso8601String(),
            'expires_at' => $report->expires_at?->toIso8601String(),
            'expired' => $expired,
            'reason_code' => $report->reason_code?->value,
            'reason_text' => $report->reason_text,
        ];
    }
}
