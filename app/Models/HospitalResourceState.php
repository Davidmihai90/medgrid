<?php

namespace App\Models;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\ResourceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'hospital_id', 'hospital_resource_definition_id', 'total_capacity', 'available_capacity', 'status', 'effective_at', 'expires_at', 'reported_by', 'source', 'notes', 'idempotency_key'])]
class HospitalResourceState extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => ResourceStatus::class, 'source' => AvailabilitySource::class, 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'created_at' => 'datetime', 'total_capacity' => 'integer', 'available_capacity' => 'integer'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(HospitalResourceDefinition::class, 'hospital_resource_definition_id');
    }
}
