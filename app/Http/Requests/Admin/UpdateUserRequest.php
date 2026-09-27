<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))], 'status' => ['nullable', Rule::in(['INVITED', 'ACTIVE', 'SUSPENDED', 'DISABLED'])], 'membership_status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])], 'role_ids' => ['required', 'array', 'min:1'], 'role_ids.*' => ['ulid', Rule::exists('roles', 'id')->where('scope', 'ORGANIZATION')]];
    }
}
