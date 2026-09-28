<?php

namespace App\Http\Requests\Clinical;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentResponsesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['responses' => ['required', 'array', 'max:100'], 'responses.*' => ['nullable']];
    }
}
