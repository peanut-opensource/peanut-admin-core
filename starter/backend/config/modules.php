<?php

declare(strict_types=1);

return [
    'kernel_version' => '1.0.0',
    'roots' => [
        'backend/src/Modules/Example/Greeting',
        'backend/src/Modules/Peanut/Settings',
        'backend/src/Modules/Peanut/ReferenceCodes',
        'backend/src/Modules/Peanut/FileMedia',
        'backend/src/Modules/Peanut/TaskJob',
        'backend/src/Modules/Peanut/NotificationSms',
        'backend/src/Modules/Peanut/ImportExport',
        'backend/src/Modules/Peanut/IntegrationSecurity',
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
