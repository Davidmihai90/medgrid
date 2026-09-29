<?php

namespace App\Http\Requests\Destination;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class ActivateRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DestinationRulesActivate, app(CurrentOrganization::class)->get()) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
