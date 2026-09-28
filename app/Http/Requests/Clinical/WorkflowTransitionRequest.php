<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Dispatch\Enums\EmergencyCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(EmergencyCaseStatus::class)]];
    }
}
