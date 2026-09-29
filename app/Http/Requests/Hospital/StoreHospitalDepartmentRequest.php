<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Identity\Support\Permissions;
use App\Models\Hospital;
use Illuminate\Validation\Rule;

class StoreHospitalDepartmentRequest extends HospitalFormRequest
{
    protected function hospital(): ?Hospital
    {
        return $this->route('hospital');
    }

    protected function permission(): string
    {
        return Permissions::HospitalDepartmentsManage;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('hospital_departments')->where('hospital_id', $this->route('hospital')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
