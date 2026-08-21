<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use PeanutAdmin\InternalStarter\Module\ModuleRegistryFactory;
use PeanutAdmin\Kernel\Package as KernelPackage;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$moduleConfig = require $root . '/backend/config/modules.php';
$registry = (new ModuleRegistryFactory($root))->compile();
$ownedTableOwners = $registry->ownedTableOwners;
ksort($ownedTableOwners);
$kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
$dataPermissionRoot = InstalledVersions::getInstallPath(DataPermissionPackage::NAME);
$kernelRoot = is_string($kernelRoot) ? $kernelRoot . '/kernel' : $kernelRoot;
$dataPermissionRoot = is_string($dataPermissionRoot) ? $dataPermissionRoot . '/data-permission' : $dataPermissionRoot;
$valid = KernelPackage::VERSION === '0.1.0'
    && DataPermissionPackage::VERSION === '0.1.0'
    && ($moduleConfig['kernel_version'] ?? null) === '1.0.0'
    && $registry->moduleKeys() === ['example.greeting', 'peanut.file-media', 'peanut.task-job', 'peanut.import-export', 'peanut.integration-security', 'peanut.notification-sms', 'peanut.reference-codes', 'peanut.settings']
    && $ownedTableOwners === [
        'pa_file_delivery_nonce' => 'peanut.file-media',
        'pa_file_delivery_policy' => 'peanut.file-media',
        'pa_file_image_metadata' => 'peanut.file-media',
        'pa_file_image_variant' => 'peanut.file-media',
        'pa_file_object' => 'peanut.file-media',
        'pa_import_export_operation' => 'peanut.import-export',
        'pa_import_export_row_error' => 'peanut.import-export',
        'pa_integration_machine_identity' => 'peanut.integration-security',
        'pa_integration_security_event' => 'peanut.integration-security',
        'pa_integration_webhook_attempt' => 'peanut.integration-security',
        'pa_integration_webhook_delivery' => 'peanut.integration-security',
        'pa_integration_webhook_endpoint' => 'peanut.integration-security',
        'pa_notification_attachment' => 'peanut.notification-sms',
        'pa_notification_event' => 'peanut.notification-sms',
        'pa_notification_message' => 'peanut.notification-sms',
        'pa_notification_outbox' => 'peanut.notification-sms',
        'pa_notification_template' => 'peanut.notification-sms',
        'pa_reference_code_entry' => 'peanut.reference-codes',
        'pa_reference_code_entry_version' => 'peanut.reference-codes',
        'pa_reference_code_set' => 'peanut.reference-codes',
        'pa_setting_definition' => 'peanut.settings',
        'pa_setting_deployment_value' => 'peanut.settings',
        'pa_setting_target_value' => 'peanut.settings',
        'pa_setting_tenant_value' => 'peanut.settings',
        'pa_sms_rate_bucket' => 'peanut.notification-sms',
        'pa_task_job' => 'peanut.task-job',
        'pa_task_job_attempt' => 'peanut.task-job',
        'pa_task_job_event' => 'peanut.task-job',
    ]
    && is_string($kernelRoot)
    && is_dir($kernelRoot . '/database/migrations')
    && is_file($kernelRoot . '/resources/schemas/module-manifest.schema.json')
    && is_string($dataPermissionRoot)
    && is_dir($dataPermissionRoot . '/database/migrations');

if (!$valid) {
    fwrite(STDERR, "ERROR: internal starter package smoke failed\n");
    exit(1);
}

fwrite(STDOUT, "Internal starter backend test: OK\n");
