<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\Enums\PatientIdentityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['identity_status' => ['required', Rule::enum(PatientIdentityStatus::class)], 'first_name' => ['nullable', 'string', 'max:120'], 'last_name' => ['nullable', 'string', 'max:120'], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'], 'estimated_age_min' => ['nullable', 'integer', 'between:0,130'], 'estimated_age_max' => ['nullable', 'integer', 'between:0,130', 'gte:estimated_age_min'], 'sex' => ['nullable', 'string', 'max:32'], 'entity_version' => ['required', 'integer', 'min:1']];
    }
}
