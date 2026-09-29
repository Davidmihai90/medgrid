<?php

namespace App\Models;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\AvailabilityStatus;
use App\Domain\Hospitals\Enums\ReasonCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'hospital_id', 'hospital_capability_id', 'status', 'reason_code', 'reason_text', 'effective_at', 'expires_at', 'reported_by', 'source', 'idempotency_key'])]
class HospitalCapabilityAvailability extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => AvailabilityStatus::class, 'reason_code' => ReasonCode::class, 'source' => AvailabilitySource::class, 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(HospitalCapability::class, 'hospital_capability_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
