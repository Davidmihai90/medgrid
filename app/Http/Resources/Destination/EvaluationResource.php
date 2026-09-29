<?php

namespace App\Http\Resources\Destination;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'reference_time' => $this->reference_time->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'rule_version' => $this->whenLoaded('ruleVersion', fn () => ['id' => $this->ruleVersion->id, 'version' => $this->ruleVersion->version, 'rule_set' => $this->ruleVersion->ruleSet->name]),
            'requirements' => RequirementResource::collection($this->whenLoaded('requirementSet', fn () => $this->requirementSet->requirements)),
            'candidates' => $this->whenLoaded('candidates', fn () => $this->candidates->map(fn ($candidate) => ['id' => $candidate->id, 'hospital' => ['id' => $candidate->hospital->id, 'code' => $candidate->hospital->code, 'name' => $candidate->hospital->name], 'outcome' => $candidate->outcome->value, 'summary' => $candidate->explanation_summary, 'evidence' => $candidate->evidence_data, 'snapshot' => $candidate->snapshot_data])),
        ];
    }
}
