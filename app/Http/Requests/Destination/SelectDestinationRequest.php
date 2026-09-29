<?php

namespace App\Http\Requests\Destination;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class SelectDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DestinationSelectionSelect, app(CurrentOrganization::class)->get()) ?? false;
    }

    public function rules(): array
    {
        return [
            'hospital_id' => ['required', 'ulid', 'exists:hospitals,id'],
            'candidate_id' => ['nullable', 'ulid', 'exists:destination_candidate_evaluations,id'],
            'expected_destination_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'min:3', 'max:3000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
