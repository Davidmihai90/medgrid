<?php

namespace App\Http\Requests\Destination;

use App\Domain\Destination\Enums\DestinationRequirementImportance;
use App\Domain\Destination\Enums\DestinationRequirementSource;
use App\Domain\Destination\Enums\DestinationRequirementType;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DestinationRequirementsManage, app(CurrentOrganization::class)->get()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['target_code' => strtoupper((string) $this->input('target_code')), 'source' => $this->input('source', 'MANUAL')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DestinationRequirementType::class)],
            'target_code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9_:-]+$/'],
            'importance' => ['required', Rule::enum(DestinationRequirementImportance::class)],
            'source' => ['required', Rule::enum(DestinationRequirementSource::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }
}
