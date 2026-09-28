<?php

namespace App\Http\Requests\Clinical;

class CorrectVitalRequest extends StoreVitalRequest
{
    public function rules(): array
    {
        return parent::rules() + ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
