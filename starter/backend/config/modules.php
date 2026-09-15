<?php

declare(strict_types=1);

return [
    'kernel_version' => '1.0.0',
    'roots' => [
        'backend/src/modules/example/greeting',
        'backend/src/modules/peanut/settings',
        'backend/src/modules/peanut/reference_codes',
        'backend/src/modules/peanut/file_media',
        'backend/src/modules/peanut/task_job',
        'backend/src/modules/peanut/notification_sms',
        'backend/src/modules/peanut/import_export',
        'backend/src/modules/peanut/integration_security',
    ],
    'frontend_components' => [
        'example.greeting.page',
        'peanut.settings.page',
        'peanut.reference-codes.page',
        'peanut.file-media.page',
        'peanut.task-job.page',
        'peanut.notification-sms.page',
        'peanut.import-export.page',
        'peanut.integration-security.page',
        'peanut.ops-console.page',
    ],
    'registered_client_keys' => [
        'operations-web',
        'reporting-web',
        'platform-web',
    ],
];
