<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', 'min:12', 'max:255'], 'status' => ['required', Rule::in(['INVITED', 'ACTIVE', 'SUSPENDED', 'DISABLED'])], 'role_ids' => ['required', 'array', 'min:1'], 'role_ids.*' => ['ulid', Rule::exists('roles', 'id')->where('scope', 'ORGANIZATION')]];
    }
}
