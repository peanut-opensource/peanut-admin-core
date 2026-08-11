<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Tests\Unit\Database;

use InvalidArgumentException;
use PeanutAdmin\Collaboration\Database\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testOwnsExactlyFourReentrantTablesInForeignKeyOrder(): void
    {
        self::assertSame([
            'pa_collaboration_session',
            'pa_collaboration_participant_lease',
            'pa_collaboration_update_envelope',
            'pa_collaboration_snapshot_envelope',
        ], Schema::tableNames());
        self::assertCount(4, Schema::createSql());

        foreach (Schema::tableNames() as $table) {
            $sql = Schema::createTableSql($table);
            self::assertStringStartsWith("CREATE TABLE IF NOT EXISTS `{$table}`", $sql);
            self::assertStringContainsString('ENGINE=InnoDB', $sql);
            self::assertStringNotContainsString('CASCADE', $sql);
        }
    }

    public function testDeclaresTenantFirstIdentityStateAndRetentionContracts(): void
    {
        $session = Schema::createTableSql('pa_collaboration_session');
        self::assertStringContainsString(
            'UNIQUE KEY `uk_collaboration_session_active_artifact` (`tenant_id`, `artifact_type`, `artifact_key`, `active_marker`)',
            $session,
        );
        self::assertStringContainsString("`status` IN ('active', 'published', 'closed', 'expired')", $session);
        self::assertStringContainsString("`session_key` REGEXP '^session_[0-9a-f]{32}$'", $session);
        self::assertStringContainsString('`base_revision_key` VARCHAR(41)', $session);
        self::assertStringContainsString('`engine_version` VARCHAR(64)', $session);
        self::assertStringContainsString("`engine_version` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$'", $session);
        self::assertStringContainsString('`published_revision_key` VARCHAR(41)', $session);
        self::assertStringNotContainsString('REFERENCES `pa_artifact', $session);

        $lease = Schema::createTableSql('pa_collaboration_participant_lease');
        self::assertStringContainsString(
            'UNIQUE KEY `uk_collaboration_lease_active_client` (`tenant_id`, `session_id`, `client_key`, `active_marker`)',
            $lease,
        );
        self::assertStringContainsString("`capability` IN ('read', 'write')", $lease);
        self::assertStringContainsString("`status` IN ('active', 'revoked', 'expired')", $lease);
        self::assertStringNotContainsString('token', strtolower($lease));
        self::assertStringNotContainsString('cookie', strtolower($lease));

        $update = Schema::createTableSql('pa_collaboration_update_envelope');
        self::assertStringContainsString(
            'UNIQUE KEY `uk_collaboration_update_sequence` (`tenant_id`, `session_id`, `sequence_no`)',
            $update,
        );
        self::assertStringContainsString('`opaque_payload` MEDIUMBLOB NOT NULL', $update);
        self::assertStringContainsString('`engine_version` VARCHAR(64)', $update);
        self::assertStringContainsString('`byte_length` = OCTET_LENGTH(`opaque_payload`)', $update);

        $snapshot = Schema::createTableSql('pa_collaboration_snapshot_envelope');
        self::assertStringContainsString(
            'KEY `idx_collaboration_snapshot_latest` (`tenant_id`, `session_id`, `covered_sequence`, `id`)',
            $snapshot,
        );
        self::assertStringContainsString('`opaque_snapshot` MEDIUMBLOB NOT NULL', $snapshot);
        self::assertStringContainsString('`opaque_state_vector` MEDIUMBLOB NOT NULL', $snapshot);
        self::assertStringContainsString('`engine_version` VARCHAR(64)', $snapshot);
        self::assertStringContainsString('`retain_until` DATETIME(3) NOT NULL', $snapshot);
    }

    public function testDropSqlIsIsolatedAndUnknownTablesFailClosed(): void
    {
        self::assertSame(
            'DROP TABLE IF EXISTS `pa_collaboration_update_envelope`',
            Schema::dropSql('pa_collaboration_update_envelope'),
        );

        $this->expectException(InvalidArgumentException::class);
        Schema::createTableSql('pa_unknown');
    }
}
