<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PeanutAdmin\OpsConsole\Task\BackupRestoreProvider;

final class ReferenceBackupRestoreProvider implements BackupRestoreProvider
{
    public function key(): string
    {
        return 'reference.mysql';
    }
    public function backupHandlerKey(): string
    {
        return 'ops.backup.reference';
    }
    public function restoreHandlerKey(): string
    {
        return 'ops.restore.reference';
    }
    public function restoreTargetKeys(): array
    {
        return ['verification'];
    }
    public function maximumAttempts(): int
    {
        return 3;
    }
}
