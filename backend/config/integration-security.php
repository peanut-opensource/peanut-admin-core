<?php

declare(strict_types=1);

$key = getenv('INTEGRATION_SECURITY_WEBHOOK_KEY');

return [
    'key_id' => getenv('INTEGRATION_SECURITY_WEBHOOK_KEY_ID') ?: 'local-dev-v1',
    'base64_key' => is_string($key) && $key !== '' ? $key : base64_encode(hash('sha256', 'peanut-admin-local-dev-webhook-key', true)),
    'machine_scopes' => ['api.read', 'api.write', 'webhook.publish'],
];
