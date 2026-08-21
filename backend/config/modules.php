<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$trustedRoots = [
    'backend/app/Modules/Peanut/Settings',
    'backend/app/Modules/Peanut/ReferenceCodes',
    'backend/app/Modules/Peanut/FileMedia',
    'backend/app/Modules/Peanut/TaskJob',
    'backend/app/Modules/Peanut/NotificationSms',
    'backend/app/Modules/Peanut/ImportExport',
    'backend/app/Modules/Peanut/IntegrationSecurity',
    'backend/app/Modules/Example/Target',
    'backend/app/Modules/Example/Reference',
    'backend/app/Modules/Example/WorkItem',
];

return [
    'kernel_version' => '1.0.0',
    'roots' => array_values(array_filter(
        $trustedRoots,
        static fn(string $path): bool => is_dir($root . '/' . $path),
    )),
    'frontend_components' => [
        'peanut.settings.page',
        'peanut.reference-codes.page',
        'peanut.file-media.page',
        'peanut.task-job.page',
        'peanut.notification-sms.page',
        'peanut.import-export.page',
        'peanut.integration-security.page',
        'example.target.list',
        'example.reference.list',
        'example.work-item.list',
        'example.work-item.policy',
    ],
];
