<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Tests\Integration\ArtifactRevision;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionReceipt;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionService;
use PeanutAdmin\ArtifactRevision\Database\Schema;
use PeanutAdmin\ArtifactRevision\Persistence\PdoArtifactRevisionRepository;
use PeanutAdmin\Collaboration\Application\CollaborationException;
use PeanutAdmin\Collaboration\ArtifactRevision\ArtifactRevisionCollaborationPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmission;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\RequestedTargetSet;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ArtifactRevisionCollaborationPublisherTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_collaboration_artifact_test';
    private const ARTIFACT_TYPE = 'document.record';
    private const ARTIFACT_KEY = 'record-publisher';

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

    public function testPinsBaseAndPublishesFinalizedChildOnTheSamePdo(): void
    {
        $tenant = $this->seedContext('req_collaboration_artifact');
        $base = $this->baseRevision($tenant);
        $repository = new PdoArtifactRevisionRepository($this->pdo);
        $publisher = new ArtifactRevisionCollaborationPublisher($repository);
        self::assertSame($this->pdo, $publisher->connection());
        $publisher->assertBaseRevision(
            $this->context($tenant, 'write'),
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            $base->revisionKey,
            (string) $base->canonicalEnvelopeSha256,
        );
        $this->assertCollaborationError('COLLABORATION_NOT_FOUND', fn() => $publisher->assertBaseRevision(
            $this->context($tenant, 'write'),
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            $base->revisionKey,
            str_repeat('f', 64),
        ));

        $submission = new CollaborationSubmission(
            'record.body',
            '1',
            'payload/record-publisher/collaboration',
            hash('sha256', 'published-collaboration-body'),
        );
        $result = $publisher->publish(
            $this->context($tenant, 'publish'),
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            $base->revisionKey,
            $submission,
            'collaboration-artifact-publish',
        );
        self::assertMatchesRegularExpression('/^revision_[0-9a-f]{32}$/D', $result['revision_key']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $result['revision_sha256']);
        $revision = $repository->revision(
            $tenant->tenantId,
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            $result['revision_key'],
        );
        self::assertNotNull($revision);
        self::assertTrue($revision->isFinalized());
        self::assertSame($base->revisionKey, $revision->parentRevisionKey);
        self::assertSame($submission->payloadRef, $revision->payloadRef);
        self::assertSame($result['revision_sha256'], $revision->canonicalEnvelopeSha256);
    }

    public function testCallerTransactionRollbackRemovesRevisionAuditAndIdempotencyTogether(): void
    {
        $tenant = $this->seedContext('req_collaboration_artifact_rollback');
        $base = $this->baseRevision($tenant);
        $publisher = new ArtifactRevisionCollaborationPublisher(new PdoArtifactRevisionRepository($this->pdo));
        $beforeAudit = (int) $this->pdo->query('SELECT COUNT(*) FROM pa_tenant_audit_event')->fetchColumn();
        $beforeIdempotency = (int) $this->pdo->query('SELECT COUNT(*) FROM pa_tenant_idempotency_record')->fetchColumn();
        $transactions = new PdoTransactionManager($this->pdo);

        try {
            $transactions->run(function () use ($publisher, $tenant, $base): void {
                $publisher->publish(
                    $this->context($tenant, 'publish'),
                    self::ARTIFACT_TYPE,
                    self::ARTIFACT_KEY,
                    $base->revisionKey,
                    new CollaborationSubmission(
                        'record.body',
                        '1',
                        'payload/record-publisher/rollback',
                        hash('sha256', 'rollback-body'),
                    ),
                    'collaboration-artifact-rollback',
                );
                throw new RuntimeException('injected caller rollback');
            });
            self::fail('The caller transaction must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('injected caller rollback', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM pa_artifact_revision')->fetchColumn());
        self::assertSame($beforeAudit, (int) $this->pdo->query('SELECT COUNT(*) FROM pa_tenant_audit_event')->fetchColumn());
        self::assertSame(
            $beforeIdempotency,
            (int) $this->pdo->query('SELECT COUNT(*) FROM pa_tenant_idempotency_record')->fetchColumn(),
        );
    }

    private function baseRevision(TenantContext $tenant): ArtifactRevisionReceipt
    {
        $service = new ArtifactRevisionService(new PdoArtifactRevisionRepository($this->pdo));
        $created = $service->createRevision(
            $this->context($tenant, 'write'),
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            null,
            null,
            'collaboration-artifact-base-create',
        );

        return $service->finalizeRevision(
            $this->context($tenant, 'write'),
            self::ARTIFACT_TYPE,
            self::ARTIFACT_KEY,
            $created->revisionKey,
            $created->artifactRevision,
            $created->revision,
            'record.body',
            '1',
            'payload/record-publisher/base',
            hash('sha256', 'artifact-base'),
            null,
            'collaboration-artifact-base-finalize',
        );
    }

    private function context(TenantContext $tenant, string $operation): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $tenant,
            self::ARTIFACT_TYPE,
            $operation,
            [new RequestedTargetSet(self::ARTIFACT_TYPE, [self::ARTIFACT_KEY])],
            hash('sha256', self::ARTIFACT_TYPE . ':' . self::ARTIFACT_KEY . ':' . $operation),
        ));
    }

    private function seedContext(string $requestId): TenantContext
    {
        $this->pdo->exec('INSERT INTO pa_tenant VALUES ()');
        $tenantId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO pa_tenant_member (id, tenant_id, account_id, status) VALUES (11, ?, 101, 'active')")
            ->execute([$tenantId]);

        return TenantContext::fromValidatedSession(new ValidatedTenantSession(
            101,
            '01J00000000000000000000000',
            $tenantId,
            101,
            11,
            'admin-web',
            new DateTimeImmutable('2031-01-01T00:00:00.000Z'),
            1,
        ), $requestId);
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
  UNIQUE KEY uk_collaboration_artifact_member (tenant_id, id)
) ENGINE=InnoDB
SQL);
        $this->pdo->exec(IdempotencySchema::tenant());
        $this->pdo->exec(KernelSchema::createSql('pa_tenant_audit_event'));
    }

    private function assertCollaborationError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$errorCode}.");
        } catch (CollaborationException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }
}
