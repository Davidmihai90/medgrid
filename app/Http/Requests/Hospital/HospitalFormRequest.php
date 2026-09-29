<?php

namespace App\Http\Requests\Hospital;

use App\Domain\Hospitals\Services\HospitalAccess;
use App\Models\Hospital;
use Illuminate\Foundation\Http\FormRequest;

abstract class HospitalFormRequest extends FormRequest
{
    abstract protected function hospital(): ?Hospital;

    abstract protected function permission(): string;

    public function authorize(): bool
    {
        $user = $this->user();
        $hospital = $this->hospital();

        return $user !== null
            && $hospital !== null
            && app(HospitalAccess::class)->allows($user, $hospital, $this->permission());
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
