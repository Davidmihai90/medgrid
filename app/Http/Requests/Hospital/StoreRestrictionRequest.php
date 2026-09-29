<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\ReasonCode;
use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;
use Illuminate\Validation\Rule;

class StoreRestrictionRequest extends HospitalFormRequest
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
            'reason_code' => ['required', Rule::enum(ReasonCode::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'effective_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
            'source' => ['required', Rule::in([AvailabilitySource::Manual->value, AvailabilitySource::Simulation->value])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
