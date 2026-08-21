<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configured = getenv('FILE_MEDIA_STORAGE_ROOT');

return [
    'provider' => 'local-private',
    'delivery_adapter' => 'local-signed',
    'delivery_base_url' => (string) (getenv('FILE_MEDIA_DELIVERY_BASE_URL') ?: 'https://starter.example.test'),
    'delivery_signing_key' => (string) (getenv('FILE_MEDIA_DELIVERY_SIGNING_KEY') ?: ''),
    'local_root' => is_string($configured) && $configured !== '' ? $configured : $root . '/storage/private/files',
    'public_roots' => [$root . '/backend/public', $root . '/frontend'],
    'max_bytes' => 10 * 1024 * 1024,
    'allowed_media_types' => ['image/png', 'image/jpeg', 'application/pdf', 'text/plain', 'text/csv'],
];
