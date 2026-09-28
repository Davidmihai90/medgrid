<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\Enums\PatientIdentityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['identity_status' => ['nullable', Rule::enum(PatientIdentityStatus::class)], 'first_name' => ['nullable', 'string', 'max:120'], 'last_name' => ['nullable', 'string', 'max:120'], 'estimated_age_min' => ['nullable', 'integer', 'min:0', 'max:130'], 'estimated_age_max' => ['nullable', 'integer', 'min:0', 'max:130', 'gte:estimated_age_min'], 'sex' => ['nullable', 'string', 'max:32']];
    }
}
