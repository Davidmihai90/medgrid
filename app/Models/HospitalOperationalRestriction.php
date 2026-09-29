<?php

namespace App\Models;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\ReasonCode;
use App\Domain\Hospitals\Enums\RestrictionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'hospital_id', 'status', 'reason_code', 'title', 'description', 'effective_at', 'expires_at', 'cancelled_at', 'reported_by', 'cancelled_by', 'source', 'idempotency_key'])]
class HospitalOperationalRestriction extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => RestrictionStatus::class, 'reason_code' => ReasonCode::class, 'source' => AvailabilitySource::class, 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'cancelled_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
