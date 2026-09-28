<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmergencyCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'case_number' => $this->case_number, 'source' => $this->source->value, 'status' => $this->status->value, 'priority' => $this->priority->value, 'incident_type' => $this->incident_type, 'location' => ['text' => $this->location_text, 'latitude' => $this->latitude, 'longitude' => $this->longitude, 'accuracy_meters' => $this->location_accuracy_meters], 'summary' => $this->summary, 'dispatcher_notes' => $this->dispatcher_notes, 'received_at' => $this->received_at?->toIso8601String(), 'version' => $this->version, 'assignment' => $this->whenLoaded('assignments', fn () => ($a = $this->assignments->sortByDesc('assigned_at')->first()) ? ['id' => $a->id, 'status' => $a->status->value, 'vehicle_id' => $a->vehicle_id] : null)];
    }
}
