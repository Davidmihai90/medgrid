<?php

namespace App\Http\Resources\Hospital;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomingNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'status' => $this->status->value,
            'notified_at' => $this->notified_at->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'case' => [
                'id' => $this->emergencyCase->id,
                'case_number' => $this->emergencyCase->case_number,
                'priority' => $this->emergencyCase->priority->value,
                'incident_type' => $this->emergencyCase->incident_type,
                'status' => $this->emergencyCase->status->value,
                'patient_count' => $this->emergencyCase->encounters_count,
                'condition_level' => $this->encounter?->condition_level?->value,
            ],
        ];
    }
}
