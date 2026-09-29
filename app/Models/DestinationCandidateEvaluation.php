<?php

namespace App\Models;

use App\Domain\Destination\Enums\DestinationCandidateOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['destination_evaluation_id', 'hospital_id', 'outcome', 'explanation_summary', 'snapshot_data', 'evidence_data'])]
class DestinationCandidateEvaluation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Completed destination candidate evidence is immutable.'));
        static::deleting(fn () => throw new LogicException('Completed destination candidate evidence is immutable.'));
    }

    protected function casts(): array
    {
        return ['outcome' => DestinationCandidateOutcome::class, 'snapshot_data' => 'array', 'evidence_data' => 'array'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(DestinationEvaluation::class, 'destination_evaluation_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
