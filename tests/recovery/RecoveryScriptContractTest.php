<?php

declare(strict_types=1);

namespace PeanutAdmin\Tests\Recovery;

use PHPUnit\Framework\TestCase;

final class RecoveryScriptContractTest extends TestCase
{
    public function testRecoveryEntrypointsExistAndAreExecutable(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'scripts/backup-mysql',
            'scripts/backup-mysql-metadata',
            'scripts/restore-mysql',
            'scripts/verify-recovery',
            'scripts/verify-clean-install',
            'scripts/test-recovery',
        ] as $path) {
            self::assertFileExists($root . '/' . $path);
            self::assertTrue(is_executable($root . '/' . $path), "{$path} must be executable.");
        }
    }

    public function testRecoveryWorkflowDeclaresOverwriteAndIntegrityStops(): void
    {
        $root = dirname(__DIR__, 2);
        $restore = (string) file_get_contents($root . '/scripts/restore-mysql');
        $backup = (string) file_get_contents($root . '/scripts/backup-mysql');

        self::assertStringContainsString('RESTORE_TARGET_IS_ACTIVE_DATABASE', $restore);
        self::assertStringContainsString('RESTORE_TARGET_ALREADY_EXISTS', $restore);
        self::assertStringContainsString('BACKUP_CHECKSUM_MISMATCH', $restore);
        self::assertStringContainsString('dump_sha256', $backup);
        self::assertStringContainsString('schema_version', $backup);
    }
}
