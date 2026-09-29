<?php

return [
    'environment_label' => env('MEDGRID_ENVIRONMENT_LABEL', strtoupper((string) env('APP_ENV', 'LOCAL'))),
    'rate_limits' => ['api' => (int) env('MEDGRID_API_RATE_LIMIT', 120)],
    // Operational demo policy, not a medical or regulatory standard.
    'hospital_freshness' => [
        'fresh_minutes' => (int) env('MEDGRID_HOSPITAL_FRESH_MINUTES', 15),
        'aging_minutes' => (int) env('MEDGRID_HOSPITAL_AGING_MINUTES', 45),
    ],
];
