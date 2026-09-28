<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

class AssignVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('emergencyCase')) ?? false;
    }

    public function rules(): array
    {
        return ['vehicle_id' => ['required', 'string', 'size:26'], 'idempotency_key' => ['nullable', 'string', 'max:100']];
    }
}
