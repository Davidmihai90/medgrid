<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('organization'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('organizations')->ignore($this->route('organization'))], 'type' => ['required', 'string', 'max:80'], 'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])], 'timezone' => ['required', 'timezone']];
    }
}
