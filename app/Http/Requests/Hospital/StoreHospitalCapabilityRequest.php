<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;

class StoreHospitalCapabilityRequest extends HospitalFormRequest
{
    protected function hospital(): ?Hospital
    {
        return $this->route('hospital');
    }

    protected function permission(): string
    {
        return Permissions::HospitalCapabilitiesManage;
    }

    public function rules(): array
    {
        return [
            'capability_definition_id' => ['required', 'ulid', 'exists:capability_definitions,id'],
            'hospital_department_id' => ['nullable', 'ulid', 'exists:hospital_departments,id'],
            'enabled' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
