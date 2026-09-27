<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Organization::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:120', 'unique:organizations,slug'], 'type' => ['required', 'string', 'max:80'], 'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])], 'timezone' => ['required', 'timezone']];
    }
}
