<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;

class UpdateHospitalCapabilityRequest extends HospitalFormRequest
{
    protected function hospital(): ?Hospital
    {
        return $this->route('capability')?->hospital;
    }

    protected function permission(): string
    {
        return Permissions::HospitalCapabilitiesManage;
    }

    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
