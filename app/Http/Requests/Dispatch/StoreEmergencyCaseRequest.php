<?php

namespace App\Http\Requests\Dispatch;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use App\Domain\Dispatch\Enums\EmergencyCaseSource;
use App\Models\EmergencyCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmergencyCase::class) ?? false;
    }

    public function rules(): array
    {
        return ['source' => ['sometimes', Rule::enum(EmergencyCaseSource::class)], 'priority' => ['sometimes', Rule::enum(EmergencyCasePriority::class)], 'incident_type' => ['required', 'string', 'max:120'], 'caller_name' => ['nullable', 'string', 'max:255'], 'caller_phone' => ['nullable', 'string', 'max:255'], 'location_text' => ['required', 'string', 'max:255'], 'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'], 'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'], 'location_accuracy_meters' => ['nullable', 'integer', 'min:0'], 'summary' => ['required', 'string', 'max:4000'], 'dispatcher_notes' => ['nullable', 'string', 'max:4000'], 'idempotency_key' => ['nullable', 'string', 'max:100']];
    }
}
