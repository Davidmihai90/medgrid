<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\Enums\ConditionLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['condition_level' => ['required', Rule::enum(ConditionLevel::class)], 'entity_version' => ['required', 'integer', 'min:1']];
    }
}
