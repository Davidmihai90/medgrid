<?php

namespace App\Domain\Destination\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DestinationRuleDefinitionValidator
{
    public function validate(array $definition): array
    {
        $validator = Validator::make($definition, [
            'engine_version' => ['required', 'string', 'max:40'],
            'receiving.limited_result' => ['required', 'in:PASS,FAIL,UNKNOWN'],
            'freshness.stale_result' => ['required', 'in:FAIL,UNKNOWN'],
            'capability.missing_result' => ['required', 'in:FAIL,UNKNOWN'],
            'capability.limited_result' => ['required', 'in:PASS,FAIL,UNKNOWN'],
            'resource.missing_result' => ['required', 'in:FAIL,UNKNOWN'],
            'resource.limited_result' => ['required', 'in:PASS,FAIL,UNKNOWN'],
            'resource.require_positive_capacity' => ['required', 'boolean'],
            'restrictions.active_result' => ['required', 'in:FAIL,UNKNOWN'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
