<?php

namespace App\Http\Requests\Dispatch;

use App\Domain\Dispatch\Enums\EmergencyCasePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriageCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('emergencyCase')) ?? false;
    }

    public function rules(): array
    {
        return ['priority' => ['required', Rule::enum(EmergencyCasePriority::class), 'not_in:UNKNOWN'], 'dispatcher_notes' => ['nullable', 'string', 'max:4000']];
    }
}
