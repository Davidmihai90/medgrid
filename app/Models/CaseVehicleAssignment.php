<?php

namespace App\Models;

use App\Domain\Dispatch\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'emergency_case_id', 'vehicle_id', 'status', 'idempotency_key', 'assigned_by', 'assigned_at', 'delivered_by', 'delivered_at', 'accepted_by', 'accepted_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class CaseVehicleAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class, 'assigned_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function emergencyCase(): BelongsTo
    {
        return $this->belongsTo(EmergencyCase::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Delivered->value, AssignmentStatus::Accepted->value]);
    }
}
