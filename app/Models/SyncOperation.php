<?php

namespace App\Models;

use App\Domain\Clinical\Enums\SyncOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'operation_id', 'operation_type', 'target_type', 'target_id', 'entity_version', 'payload_hash', 'outcome', 'resource_type', 'resource_id', 'device_id', 'session_id', 'submitted_by', 'captured_at', 'processed_at'])] class SyncOperation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['outcome' => SyncOutcome::class, 'captured_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
