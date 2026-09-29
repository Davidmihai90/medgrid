<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\ReasonCode;
use App\Domain\Hospitals\Enums\ReceivingStatus;
use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;
use Illuminate\Validation\Rule;

class StoreReceivingStatusRequest extends HospitalFormRequest
{
    protected function hospital(): ?Hospital
    {
        return $this->route('hospital');
    }

    protected function permission(): string
    {
        return Permissions::HospitalAvailabilityUpdate;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReceivingStatus::class)],
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
