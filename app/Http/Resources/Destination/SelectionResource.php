<?php

namespace App\Http\Resources\Destination;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'hospital' => ['id' => $this->hospital->id, 'code' => $this->hospital->code, 'name' => $this->hospital->name], 'selection_type' => $this->selection_type->value, 'reason' => $this->reason, 'selected_by' => $this->selected_by, 'evaluation_id' => $this->destination_evaluation_id, 'candidate_id' => $this->destination_candidate_evaluation_id, 'supersedes_selection_id' => $this->supersedes_selection_id, 'superseded_at' => $this->superseded_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String()];
    }
}
