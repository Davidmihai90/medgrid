<?php

namespace App\Http\Requests\Clinical;

use Illuminate\Foundation\Http\FormRequest;

class SyncOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['operations' => ['required', 'array', 'min:1', 'max:50'], 'operations.*.operation_id' => ['required', 'ulid'], 'operations.*.operation_type' => ['required', 'in:VITAL_CREATE,CONDITION_UPDATE'], 'operations.*.target_id' => ['required', 'ulid'], 'operations.*.entity_version' => ['nullable', 'integer', 'min:1'], 'operations.*.payload' => ['required', 'array'], 'operations.*.captured_at' => ['required', 'date'], 'operations.*.device_id' => ['nullable', 'string', 'max:120']];
    }
}
