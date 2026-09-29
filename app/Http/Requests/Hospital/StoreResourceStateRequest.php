<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Enums\AvailabilitySource;
use App\Domain\Hospitals\Enums\ResourceStatus;
use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResourceStateRequest extends HospitalFormRequest
{
    protected function hospital(): ?Hospital
    {
        return $this->route('hospital');
    }

    protected function permission(): string
    {
        return Permissions::HospitalResourcesUpdate;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ResourceStatus::class)],
            'total_capacity' => ['nullable', 'integer', 'min:0'],
            'available_capacity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'effective_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
            'source' => ['required', Rule::in([AvailabilitySource::Manual->value, AvailabilitySource::Simulation->value])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('total_capacity') && $this->filled('available_capacity') && $this->integer('available_capacity') > $this->integer('total_capacity')) {
                $validator->errors()->add('available_capacity', 'Available capacity cannot exceed total capacity.');
            }
        }];
    }
}
