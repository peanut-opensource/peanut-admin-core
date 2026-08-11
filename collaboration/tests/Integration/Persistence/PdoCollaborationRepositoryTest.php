<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Tests\Integration\Persistence;

use PDO;
use PeanutAdmin\Collaboration\Database\Schema;
use PeanutAdmin\Collaboration\Persistence\PdoCollaborationRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

final class PdoCollaborationRepositoryTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_collaboration_repository_test';

    private PDO $admin;
    private PDO $pdo;

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through P1-COLLABORATION-001 MySQL qualification.');
        }
        $port = (int) (getenv('DB_PORT') ?: 0);
        if (getenv('DB_HOST') !== '127.0.0.1'
            || $port < 1024
            || $port > 65535
            || $port !== (int) getenv('MYSQL_PORT')) {
            throw new RuntimeException('Collaboration qualification requires an explicit local MySQL port.');
        }
        $password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $this->createKernelFixtures();
        foreach (Schema::createSql() as $statement) {
            $this->pdo->exec($statement);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
    }

    public function testSchemaCanReenterWithoutChangingKernelOrCollaborationRows(): void
    {
        [$tenantId] = $this->seedTenant(11, 101);
        $repository = new PdoCollaborationRepository($this->pdo);
        $repository->createSession(
            $tenantId,
            $this->sessionKey('a'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        );

        foreach (Schema::createSql() as $statement) {
            $this->pdo->exec($statement);
        }

        self::assertSame([
            'pa_collaboration_participant_lease',
            'pa_collaboration_session',
            'pa_collaboration_snapshot_envelope',
            'pa_collaboration_update_envelope',
        ], $this->collaborationTables());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM pa_collaboration_session')->fetchColumn());
    }

    public function testPersistsOrderedOpaqueEnvelopesSnapshotLeaseAndPublishedState(): void
    {
        [$tenantId] = $this->seedTenant(11, 101);
        $repository = new PdoCollaborationRepository($this->pdo);
        self::assertSame($this->pdo, $repository->connection());
        $session = $repository->createSession(
            $tenantId,
            $this->sessionKey('a'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        );
        self::assertTrue($session->isActive());
        self::assertSame(0, $session->latestSequence);
        self::assertSame($session->id, $repository->activeSession(
            $tenantId,
            'document.record',
            'record-1',
        )?->id);

        $lease = $repository->createLease(
            $tenantId,
            $session->id,
            $this->leaseKey('d'),
            'desktop-client-1',
            11,
            101,
            'write',
            $this->digest('e'),
            $this->later(120),
            $this->now(),
        );
        self::assertSame($lease->id, $repository->activeLeaseForClient(
            $tenantId,
            $session->id,
            'desktop-client-1',
        )?->id);

        $firstPayload = "\x01yjs-update-one";
        $first = $repository->appendUpdate(
            $tenantId,
            $session->id,
            1,
            0,
            $this->updateKey('f'),
            $lease->clientKey,
            $lease->leaseKey,
            'yjs',
            '13.6.32',
            $firstPayload,
            hash('sha256', $firstPayload),
            11,
            101,
            $this->now(),
        );
        $secondPayload = "\x02yjs-update-two";
        $second = $repository->appendUpdate(
            $tenantId,
            $session->id,
            2,
            1,
            $this->updateKey('1'),
            $lease->clientKey,
            $lease->leaseKey,
            'yjs',
            '13.6.32',
            $secondPayload,
            hash('sha256', $secondPayload),
            11,
            101,
            $this->now(),
        );
        self::assertSame([1, 2], array_map(
            static fn($update): int => $update->sequenceNo,
            $repository->updatesAfter($tenantId, $session->id, 0, 100),
        ));
        self::assertSame(
            ['count' => 1, 'bytes' => strlen($secondPayload)],
            $repository->updateWindowAfter($tenantId, $session->id, 1),
        );
        self::assertSame($firstPayload, $first->opaquePayload);
        self::assertSame(2, $second->sequenceNo);

        $snapshotPayload = "\x03opaque-yjs-snapshot";
        $stateVector = "\x04opaque-state-vector";
        $snapshot = $repository->saveSnapshot(
            $tenantId,
            $session->id,
            3,
            $this->snapshotKey('2'),
            2,
            'yjs',
            '13.6.32',
            $snapshotPayload,
            hash('sha256', $snapshotPayload),
            $stateVector,
            hash('sha256', $stateVector),
            11,
            101,
            $this->now(),
            $this->later(600),
        );
        self::assertSame($snapshot->id, $repository->latestSnapshot($tenantId, $session->id)?->id);
        self::assertSame(2, $snapshot->coveredSequence);

        $lease = $repository->heartbeatLease(
            $tenantId,
            $session->id,
            $lease->leaseKey,
            1,
            $this->later(180),
            $this->later(1),
        );
        self::assertSame(2, $lease->revision);

        $published = $repository->completeSession(
            $tenantId,
            $session->id,
            4,
            'published',
            11,
            101,
            $this->revisionKey('3'),
            $this->digest('4'),
            $this->later(2),
            $this->later(1200),
        );
        self::assertSame('published', $published->status);
        self::assertSame($this->revisionKey('3'), $published->publishedRevisionKey);
        self::assertSame(1, $repository->revokeLeases($tenantId, $session->id, $this->later(2)));
        self::assertSame(1, $repository->retainSnapshotsUntil($tenantId, $session->id, $this->later(1200)));
        self::assertSame('revoked', $repository->lease(
            $tenantId,
            $session->id,
            $lease->leaseKey,
        )?->status);
    }

    public function testTenantAndActiveArtifactBoundariesFailClosed(): void
    {
        [$tenantId] = $this->seedTenant(11, 101);
        [$otherTenantId] = $this->seedTenant(21, 201);
        $repository = new PdoCollaborationRepository($this->pdo);
        $session = $repository->createSession(
            $tenantId,
            $this->sessionKey('a'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        );

        self::assertNull($repository->session($otherTenantId, $session->sessionKey));
        self::assertNull($repository->activeSession($otherTenantId, 'document.record', 'record-1'));
        $this->assertRuntimeFailure(fn() => $repository->createSession(
            $tenantId,
            $this->sessionKey('d'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        ));

        $repository->completeSession(
            $tenantId,
            $session->id,
            1,
            'closed',
            11,
            101,
            null,
            null,
            $this->later(1),
            $this->later(600),
        );
        $replacement = $repository->createSession(
            $tenantId,
            $this->sessionKey('d'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('e'),
            $this->digest('f'),
            11,
            101,
            $this->later(3600),
            $this->later(2),
        );
        self::assertNotSame($session->sessionKey, $replacement->sessionKey);
    }

    public function testRejectsStaleLeaseSequenceFutureSnapshotAndTamperedEnvelope(): void
    {
        [$tenantId] = $this->seedTenant(11, 101);
        $repository = new PdoCollaborationRepository($this->pdo);
        $session = $repository->createSession(
            $tenantId,
            $this->sessionKey('a'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        );
        $lease = $repository->createLease(
            $tenantId,
            $session->id,
            $this->leaseKey('d'),
            'desktop-client-1',
            11,
            101,
            'write',
            $this->digest('e'),
            $this->later(120),
            $this->now(),
        );
        $payload = 'opaque-update';
        $repository->appendUpdate(
            $tenantId,
            $session->id,
            1,
            0,
            $this->updateKey('f'),
            $lease->clientKey,
            $lease->leaseKey,
            'yjs',
            '13.6.32',
            $payload,
            hash('sha256', $payload),
            11,
            101,
            $this->now(),
        );

        $this->assertRuntimeFailure(fn() => $repository->appendUpdate(
            $tenantId,
            $session->id,
            1,
            0,
            $this->updateKey('1'),
            $lease->clientKey,
            $lease->leaseKey,
            'yjs',
            '13.6.32',
            $payload,
            hash('sha256', $payload),
            11,
            101,
            $this->now(),
        ));
        $this->assertRuntimeFailure(fn() => $repository->saveSnapshot(
            $tenantId,
            $session->id,
            2,
            $this->snapshotKey('2'),
            2,
            'yjs',
            '13.6.32',
            'snapshot',
            hash('sha256', 'snapshot'),
            'vector',
            hash('sha256', 'vector'),
            11,
            101,
            $this->now(),
            $this->later(600),
        ));

        $this->pdo->exec("UPDATE pa_collaboration_update_envelope SET update_sha256 = REPEAT('0', 64)");
        try {
            $repository->updateEnvelope($tenantId, $session->id, $this->updateKey('f'));
            self::fail('A tampered collaboration update must fail closed.');
        } catch (UnexpectedValueException $exception) {
            self::assertStringNotContainsString($payload, $exception->getMessage());
        }
    }

    public function testExpiresAndRevokesOnlyTenantSessionLeases(): void
    {
        [$tenantId] = $this->seedTenant(11, 101);
        $repository = new PdoCollaborationRepository($this->pdo);
        $session = $repository->createSession(
            $tenantId,
            $this->sessionKey('a'),
            'document.record',
            'record-1',
            'yjs',
            '13.6.32',
            $this->revisionKey('b'),
            $this->digest('c'),
            11,
            101,
            $this->later(3600),
            $this->now(),
        );
        $lease = $repository->createLease(
            $tenantId,
            $session->id,
            $this->leaseKey('d'),
            'desktop-client-1',
            11,
            101,
            'read',
            $this->digest('e'),
            $this->later(30),
            $this->now(),
        );

        self::assertSame(0, $repository->expireLeases($tenantId, $session->id, $this->later(29)));
        self::assertSame(1, $repository->expireLeases($tenantId, $session->id, $this->later(30)));
        self::assertSame('expired', $repository->lease($tenantId, $session->id, $lease->leaseKey)?->status);
        self::assertSame(0, $repository->revokeLeases($tenantId, $session->id, $this->later(31)));
    }

    private function createKernelFixtures(): void
    {
        $this->pdo->exec('CREATE TABLE pa_tenant (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) ENGINE=InnoDB');
        $this->pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant_member (
  id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_collaboration_test_member (tenant_id, id)
) ENGINE=InnoDB
SQL);
    }

    /** @return array{int, int} */
    private function seedTenant(int $memberId, int $accountId): array
    {
        $this->pdo->exec('INSERT INTO pa_tenant VALUES ()');
        $tenantId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO pa_tenant_member (id, tenant_id, account_id, status) VALUES (?, ?, ?, 'active')")
            ->execute([$memberId, $tenantId, $accountId]);

        return [$tenantId, $memberId];
    }

    /** @return list<string> */
    private function collaborationTables(): array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'pa_collaboration%'
ORDER BY table_name
SQL);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function assertRuntimeFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('The collaboration repository operation must fail closed.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
    }

    private function sessionKey(string $digit): string
    {
        return 'session_' . str_repeat($digit, 32);
    }

    private function leaseKey(string $digit): string
    {
        return 'lease_' . str_repeat($digit, 32);
    }

    private function updateKey(string $digit): string
    {
        return 'update_' . str_repeat($digit, 32);
    }

    private function snapshotKey(string $digit): string
    {
        return 'snapshot_' . str_repeat($digit, 32);
    }

    private function revisionKey(string $digit): string
    {
        return 'revision_' . str_repeat($digit, 32);
    }

    private function digest(string $digit): string
    {
        return str_repeat($digit, 64);
    }

    private function now(): string
    {
        return '2026-08-12 00:00:00.000';
    }

    private function later(int $seconds): string
    {
        return (new \DateTimeImmutable($this->now(), new \DateTimeZone('UTC')))
            ->modify("+{$seconds} seconds")
            ->format('Y-m-d H:i:s.v');
    }
}
