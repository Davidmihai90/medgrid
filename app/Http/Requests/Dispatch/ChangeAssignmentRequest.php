<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

class ChangeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicleRules = $this->isMethod('PUT')
            ? ['required', 'string', 'size:26']
            : ['prohibited'];

        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'vehicle_id' => $vehicleRules,
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
