<?php

namespace App\Http\Requests\Destination;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class StoreRuleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DestinationRulesManage, app(CurrentOrganization::class)->get()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper((string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9_-]+$/'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:3000'], 'is_synthetic' => ['sometimes', 'boolean']];
    }
}
