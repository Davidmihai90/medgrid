<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Enums\HospitalStatus;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(CurrentOrganization::class)->get();

        return $organization && $this->user()?->hasPermission(Permissions::HospitalsManage, $organization);
    }

    public function rules(): array
    {
        $organization = app(CurrentOrganization::class)->get();

        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('hospitals')->where('organization_id', $organization?->id)],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(HospitalStatus::class)],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'timezone' => ['required', 'timezone'],
            'phone' => ['nullable', 'string', 'max:80'],
            'active' => ['required', 'boolean'],
        ];
    }
}
