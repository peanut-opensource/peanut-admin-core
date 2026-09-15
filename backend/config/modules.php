<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$trustedRoots = [
    'backend/app/modules/peanut/settings',
    'backend/app/modules/peanut/reference_codes',
    'backend/app/modules/peanut/file_media',
    'backend/app/modules/peanut/task_job',
    'backend/app/modules/peanut/notification_sms',
    'backend/app/modules/peanut/import_export',
    'backend/app/modules/peanut/integration_security',
    'backend/app/modules/example/target',
    'backend/app/modules/example/reference',
    'backend/app/modules/example/work_item',
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
