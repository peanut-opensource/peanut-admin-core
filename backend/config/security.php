<?php

declare(strict_types=1);

return [
    'cors' => [
        'allowed_origins' => [],
        'allow_credentials' => false,
    ],
    'headers' => [
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Cache-Control' => 'no-store',
    ],
];
