<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\AvailabilityStatus;
use App\Domain\Hospitals\Enums\ReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCapabilityAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AvailabilityStatus::class)],
            'reason_code' => ['nullable', Rule::enum(ReasonCode::class)],
            'reason_text' => ['nullable', 'string', 'max:1000'],
            'effective_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
            'source' => ['required', Rule::in([AvailabilitySource::Manual->value, AvailabilitySource::Simulation->value])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
