<?php

namespace App\Models;

use App\Domain\Hospitals\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'hospital_id', 'emergency_case_id', 'patient_encounter_id', 'status', 'notified_at', 'delivered_at', 'acknowledged_at', 'acknowledged_by', 'cancelled_at', 'created_by', 'idempotency_key'])]
class HospitalCaseNotification extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => NotificationStatus::class, 'notified_at' => 'datetime', 'delivered_at' => 'datetime', 'acknowledged_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function emergencyCase(): BelongsTo
    {
        return $this->belongsTo(EmergencyCase::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PatientEncounter::class, 'patient_encounter_id');
    }
}
