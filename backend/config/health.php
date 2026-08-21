<?php

declare(strict_types=1);

return [
    'cache_timeout_seconds' => (float) (getenv('HEALTH_CACHE_TIMEOUT') ?: 0.5),
    'cache_is_critical' => false,
    'expose_failure_details' => false,
];
