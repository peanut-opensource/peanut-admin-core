<?php

declare(strict_types=1);

$directory = json_decode((string) (getenv('PEANUT_SMS_RECIPIENTS_JSON') ?: '{}'), true);

return [
    'envelope_key' => (string) (getenv('PEANUT_TASK_ENVELOPE_KEY') ?: ''),
    'recipient_digest_key' => (string) (getenv('PEANUT_SMS_RECIPIENT_DIGEST_KEY') ?: ''),
    'recipient_directory' => is_array($directory) ? $directory : [],
];
