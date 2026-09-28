<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\Enums\ObservationSource;
use App\Domain\Clinical\Enums\VitalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(VitalType::class)], 'value_numeric' => ['nullable', 'numeric'], 'secondary_value_numeric' => ['nullable', 'numeric'], 'unit' => ['nullable', 'string', 'max:32'], 'gcs_eye' => ['nullable', 'integer', 'between:1,4'], 'gcs_verbal' => ['nullable', 'integer', 'between:1,5'], 'gcs_motor' => ['nullable', 'integer', 'between:1,6'], 'gcs_total' => ['nullable', 'integer', 'between:3,15'], 'measured_at' => ['required', 'date', 'before_or_equal:now'], 'source' => ['nullable', Rule::enum(ObservationSource::class)], 'device_identifier' => ['nullable', 'string', 'max:120'], 'operation_id' => ['nullable', 'ulid'], 'supersedes_observation_id' => ['prohibited'], 'correction_reason' => ['prohibited']];
    }

    public function after(): array
    {
        return [function ($validator) {
            $d = $this->all();
            if (($d['type'] ?? null) === 'BLOOD_PRESSURE' && (! isset($d['value_numeric'],$d['secondary_value_numeric']) || ($d['unit'] ?? null) !== 'mmHg')) {
                $validator->errors()->add('value_numeric', 'Blood pressure requires systolic, diastolic, and mmHg.');
            }if (($d['type'] ?? null) === 'GCS' && ! isset($d['gcs_total']) && (! isset($d['gcs_eye'],$d['gcs_verbal'],$d['gcs_motor']))) {
                $validator->errors()->add('gcs_total', 'GCS requires a total or all three components.');
            }if (($d['type'] ?? null) === 'GLUCOSE' && ! in_array($d['unit'] ?? null, ['mg/dL', 'mmol/L'], true)) {
                $validator->errors()->add('unit', 'Glucose unit must be mg/dL or mmol/L.');
            }if (! in_array($d['type'] ?? null, ['BLOOD_PRESSURE', 'GCS'], true) && ! isset($d['value_numeric'])) {
                $validator->errors()->add('value_numeric', 'A numeric value is required for this vital type.');
            }
        }];
    }
}
