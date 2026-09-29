<?php

namespace App\Http\Resources\Destination;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type->value, 'target_code' => $this->target_code, 'importance' => $this->importance->value, 'source' => $this->source->value, 'status' => $this->status->value, 'notes' => $this->notes, 'entered_by' => $this->entered_by, 'confirmed_by' => $this->confirmed_by, 'supersedes_requirement_id' => $this->supersedes_requirement_id, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
