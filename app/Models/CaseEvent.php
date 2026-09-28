<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['organization_id', 'emergency_case_id', 'event_type', 'event_version', 'actor_type', 'actor_id', 'occurred_at', 'summary', 'metadata', 'correlation_id'])]
class CaseEvent extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Case timeline events are append-only.'));
        static::deleting(fn () => throw new LogicException('Case timeline events are append-only.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    public function emergencyCase(): BelongsTo
    {
        return $this->belongsTo(EmergencyCase::class);
    }
}
