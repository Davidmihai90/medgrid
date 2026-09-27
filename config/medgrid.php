<?php

return [
    'environment_label' => env('MEDGRID_ENVIRONMENT_LABEL', strtoupper((string) env('APP_ENV', 'LOCAL'))),
    'rate_limits' => ['api' => (int) env('MEDGRID_API_RATE_LIMIT', 120)],
];
