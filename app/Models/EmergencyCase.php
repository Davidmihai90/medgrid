<?php

namespace App\Models;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['case_number', 'organization_id', 'source', 'status', 'priority', 'incident_type', 'caller_name', 'caller_phone', 'location_text', 'latitude', 'longitude', 'location_accuracy_meters', 'summary', 'dispatcher_notes', 'received_at', 'triaged_at', 'assigned_at', 'closed_at', 'version', 'idempotency_key', 'created_by', 'updated_by'])]
class EmergencyCase extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['source' => EmergencyCaseSource::class, 'status' => EmergencyCaseStatus::class, 'priority' => EmergencyCasePriority::class, 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'received_at' => 'immutable_datetime', 'triaged_at' => 'immutable_datetime', 'assigned_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class)->orderByDesc('occurred_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CaseVehicleAssignment::class);
    }

    public function scopeForOrganization(Builder $q, Organization $o): Builder
    {
        return $q->where('organization_id', $o->id);
    }
}
