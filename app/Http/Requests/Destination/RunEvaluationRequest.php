<?php

namespace App\Http\Requests\Destination;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class RunEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DestinationEvaluationsCreate, app(CurrentOrganization::class)->get()) ?? false;
    }

    public function rules(): array
    {
        return [
            'rule_version_id' => ['required', 'ulid', 'exists:destination_rule_set_versions,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
