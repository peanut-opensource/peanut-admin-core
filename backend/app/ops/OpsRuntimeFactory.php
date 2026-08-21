<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProviderRegistry;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogService;
use PeanutAdmin\OpsConsole\Logs\SafeLogMessageCatalog;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceReasonRegistry;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceService;
use PeanutAdmin\OpsConsole\Status\OpsStatusService;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;
use PeanutAdmin\OpsConsole\Task\OpsTaskService;

final class OpsRuntimeFactory
{
    public static function status(PDO $pdo): OpsStatusService
    {
        return new OpsStatusService(new PdoPlatformPermissionChecker($pdo), new HostRuntimeStatusProvider($pdo, dirname(__DIR__, 3)));
    }
    public static function tasks(PDO $pdo): OpsTaskService
    {
        return new OpsTaskService(new PdoPlatformPermissionChecker($pdo), new BackupRestoreProviderRegistry([new ReferenceBackupRestoreProvider()]), new PdoOpsTaskDispatcher($pdo));
    }
    public static function maintenance(PDO $pdo): MaintenanceService
    {
        return new MaintenanceService(new PdoPlatformPermissionChecker($pdo), new MaintenanceReasonRegistry(['planned-upgrade','database-maintenance','security-maintenance']), new PdoMaintenanceWindowStore($pdo));
    }
    public static function logs(PDO $pdo): RuntimeLogService
    {
        return new RuntimeLogService(new PdoPlatformPermissionChecker($pdo), new RuntimeLogProviderRegistry([new PdoRuntimeLogProvider($pdo)]), new SafeLogMessageCatalog(['platform.ops.maintenance.scheduled' => 'A maintenance window was scheduled.','platform.ops.maintenance.closed' => 'A maintenance window was closed.','platform.ops.backup.submitted' => 'A backup task was submitted.','platform.ops.restore.submitted' => 'A restore verification task was submitted.']));
    }
}
