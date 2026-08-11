<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Tests\Integration\Application;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionReceipt;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionService;
use PeanutAdmin\ArtifactRevision\Database\Schema as ArtifactRevisionSchema;
use PeanutAdmin\ArtifactRevision\Persistence\PdoArtifactRevisionRepository;
use PeanutAdmin\Collaboration\Application\CollaborationException;
use PeanutAdmin\Collaboration\Application\CollaborationService;
use PeanutAdmin\Collaboration\ArtifactRevision\ArtifactRevisionCollaborationPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicy;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicyProvider;
use PeanutAdmin\Collaboration\Contract\CollaborationRevisionPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmission;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmissionProvider;
use PeanutAdmin\Collaboration\Database\Schema as CollaborationSchema;
use PeanutAdmin\Collaboration\Persistence\PdoCollaborationRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\RequestedTargetSet;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class CollaborationServiceTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_collaboration_service_test';
    private const ARTIFACT_TYPE = 'document.record';

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
        foreach ([...ArtifactRevisionSchema::createSql(), ...CollaborationSchema::createSql()] as $statement) {
            $this->pdo->exec($statement);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
    }

    public function testPublicSurfaceContainsExactlyEightOperations(): void
    {
        self::assertSame(
            [
                'openSession',
                'joinSession',
                'heartbeat',
                'appendUpdate',
                'saveSnapshot',
                'state',
                'publish',
                'closeSession',
            ],
            array_values(array_map(
                static fn(ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    (new ReflectionClass(CollaborationService::class))->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn(ReflectionMethod $method): bool => $method->getDeclaringClass()->getName()
                        === CollaborationService::class && $method->getName() !== '__construct',
                ),
            )),
        );
    }

    public function testEightOperationsAreTenantScopedReplaySafeAndPublishOneRevision(): void
    {
        [$tenantId, $tenant] = $this->seedContext('req_collaboration_eight');
        $base = $this->baseRevision($tenant, 'record-1');
        $service = $this->service();

        $opened = $service->openSession(
            $this->context($tenant, 'record-1', 'write'),
            self::ARTIFACT_TYPE,
            'record-1',
            'yjs',
            '13.6.32',
            $base->revisionKey,
            (string) $base->canonicalEnvelopeSha256,
            'collaboration-open-0001',
        );
        $openReplay = $service->openSession(
            $this->context($tenant, 'record-1', 'write'),
            self::ARTIFACT_TYPE,
            'record-1',
            'yjs',
            '13.6.32',
            $base->revisionKey,
            (string) $base->canonicalEnvelopeSha256,
            'collaboration-open-0001',
        );
        self::assertSame($opened->toArray(), $openReplay->toArray());

        $joined = $service->joinSession(
            $this->context($tenant, 'record-1', 'write'),
            $opened->sessionKey,
            'browser-client-1',
            'write',
            'collaboration-join-0001',
        );
        $heartbeat = $service->heartbeat(
            $this->context($tenant, 'record-1', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            'collaboration-heartbeat-01',
        );
        self::assertSame($joined->leaseKey, $heartbeat->leaseKey);

        $updatePayload = "opaque-yjs-update\0bytes";
        $update = $service->appendUpdate(
            $this->context($tenant, 'record-1', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            'browser-client-1',
            0,
            $updatePayload,
            hash('sha256', $updatePayload),
            'collaboration-append-001',
        );
        $snapshotPayload = "opaque-yjs-snapshot\0bytes";
        $stateVector = "opaque-state-vector\0bytes";
        $snapshot = $service->saveSnapshot(
            $this->context($tenant, 'record-1', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            1,
            $snapshotPayload,
            hash('sha256', $snapshotPayload),
            $stateVector,
            hash('sha256', $stateVector),
            'collaboration-snapshot-01',
        );
        self::assertSame(1, $update->sequence);
        self::assertSame(1, $snapshot->coveredSequence);

        $state = $service->state(
            $this->context($tenant, 'record-1', 'read'),
            $opened->sessionKey,
            0,
            50,
        );
        self::assertSame($snapshotPayload, $state->snapshot?->opaqueSnapshot);
        self::assertSame([], $state->updates);
        self::assertSame(1, $state->nextAfterSequence);
        $incrementalState = $service->state(
            $this->context($tenant, 'record-1', 'read'),
            $opened->sessionKey,
            1,
            50,
        );
        self::assertNull($incrementalState->snapshot);
        self::assertSame([], $incrementalState->updates);
        self::assertSame(1, $incrementalState->nextAfterSequence);

        $published = $service->publish(
            $this->context($tenant, 'record-1', 'publish'),
            $opened->sessionKey,
            'collaboration-publish-001',
        );
        $publishReplay = $service->publish(
            $this->context($tenant, 'record-1', 'publish'),
            $opened->sessionKey,
            'collaboration-publish-001',
        );
        self::assertSame($published->toArray(), $publishReplay->toArray());
        self::assertSame('published', $published->sessionStatus);
        self::assertNotNull($published->publishedRevisionKey);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM pa_artifact_revision')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM pa_collaboration_participant_lease WHERE status = 'active'",
        )->fetchColumn());
        self::assertSame($tenantId, (int) $this->pdo->query(
            'SELECT tenant_id FROM pa_collaboration_session LIMIT 1',
        )->fetchColumn());

        $otherBase = $this->baseRevision($tenant, 'record-2');
        $other = $service->openSession(
            $this->context($tenant, 'record-2', 'write'),
            self::ARTIFACT_TYPE,
            'record-2',
            'yjs',
            '13.6.32',
            $otherBase->revisionKey,
            (string) $otherBase->canonicalEnvelopeSha256,
            'collaboration-open-0002',
        );
        $closed = $service->closeSession(
            $this->context($tenant, 'record-2', 'write'),
            $other->sessionKey,
            'collaboration-close-0001',
        );
        self::assertSame('closed', $closed->sessionStatus);

        $auditJson = (string) $this->pdo->query(
            'SELECT JSON_ARRAYAGG(metadata_json) FROM pa_tenant_audit_event',
        )->fetchColumn();
        self::assertStringNotContainsString($updatePayload, $auditJson);
        self::assertStringNotContainsString($snapshotPayload, $auditJson);
        self::assertStringNotContainsString($stateVector, $auditJson);
        self::assertStringNotContainsString('payload/record-1/published', $auditJson);
    }

    public function testPolicyBackpressureAndProviderFailuresFailClosed(): void
    {
        [, $tenant] = $this->seedContext('req_collaboration_policy');
        $base = $this->baseRevision($tenant, 'record-policy');
        $policy = new CollaborationPolicy(3_600, 60, 32, 64, 1, 3_600);
        $service = $this->service($policy);
        $opened = $service->openSession(
            $this->context($tenant, 'record-policy', 'write'),
            self::ARTIFACT_TYPE,
            'record-policy',
            'yjs',
            '13.6.32',
            $base->revisionKey,
            (string) $base->canonicalEnvelopeSha256,
            'collaboration-policy-open',
        );
        $joined = $service->joinSession(
            $this->context($tenant, 'record-policy', 'write'),
            $opened->sessionKey,
            'client-policy',
            'write',
            'collaboration-policy-join',
        );
        $first = 'first';
        $service->appendUpdate(
            $this->context($tenant, 'record-policy', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            'client-policy',
            0,
            $first,
            hash('sha256', $first),
            'collaboration-policy-first',
        );
        $second = 'second';
        $this->assertCollaborationError('COLLABORATION_BACKPRESSURE', fn() => $service->appendUpdate(
            $this->context($tenant, 'record-policy', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            'client-policy',
            1,
            $second,
            hash('sha256', $second),
            'collaboration-policy-second',
        ));

        $denying = $this->service($policy, policyMode: 'deny');
        $this->assertCollaborationError('COLLABORATION_DENIED', fn() => $denying->state(
            $this->context($tenant, 'record-policy', 'read'),
            $opened->sessionKey,
            0,
        ));
        $failing = $this->service($policy, policyMode: 'fail');
        $this->assertCollaborationError('COLLABORATION_PROVIDER_UNAVAILABLE', fn() => $failing->state(
            $this->context($tenant, 'record-policy', 'read'),
            $opened->sessionKey,
            0,
        ));
    }

    public function testPublishFailureRollsBackProviderEffectsAndSessionTransition(): void
    {
        [, $tenant] = $this->seedContext('req_collaboration_rollback');
        $base = $this->baseRevision($tenant, 'record-rollback');
        $publisher = new FailingCollaborationPublisher($this->pdo);
        $service = $this->service(publisher: $publisher);
        $opened = $service->openSession(
            $this->context($tenant, 'record-rollback', 'write'),
            self::ARTIFACT_TYPE,
            'record-rollback',
            'yjs',
            '13.6.32',
            $base->revisionKey,
            (string) $base->canonicalEnvelopeSha256,
            'collaboration-rollback-open',
        );
        $joined = $service->joinSession(
            $this->context($tenant, 'record-rollback', 'write'),
            $opened->sessionKey,
            'client-rollback',
            'write',
            'collaboration-rollback-join',
        );
        $snapshot = 'empty-but-valid-snapshot';
        $vector = 'empty-but-valid-vector';
        $service->saveSnapshot(
            $this->context($tenant, 'record-rollback', 'write'),
            $opened->sessionKey,
            (string) $joined->leaseKey,
            0,
            $snapshot,
            hash('sha256', $snapshot),
            $vector,
            hash('sha256', $vector),
            'collaboration-rollback-snap',
        );

        $this->assertCollaborationError('COLLABORATION_PROVIDER_UNAVAILABLE', fn() => $service->publish(
            $this->context($tenant, 'record-rollback', 'publish'),
            $opened->sessionKey,
            'collaboration-rollback-pub',
        ));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM collaboration_publish_probe')->fetchColumn());
        self::assertSame('active', (string) $this->pdo->query(
            "SELECT status FROM pa_collaboration_session WHERE session_key = '{$opened->sessionKey}'",
        )->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM pa_collaboration_participant_lease WHERE status = 'active'",
        )->fetchColumn());
    }

    private function service(
        ?CollaborationPolicy $policy = null,
        string $policyMode = 'allow',
        ?CollaborationRevisionPublisher $publisher = null,
    ): CollaborationService {
        $policy ??= new CollaborationPolicy(3_600, 60, 262_144, 8_388_608, 1_000, 3_600);
        $publisher ??= new ArtifactRevisionCollaborationPublisher(new PdoArtifactRevisionRepository($this->pdo));

        return new CollaborationService(
            new PdoCollaborationRepository($this->pdo),
            new TestCollaborationPolicyProvider($this->pdo, $policy, $policyMode),
            new TestCollaborationSubmissionProvider($this->pdo),
            $publisher,
            static fn(): DateTimeImmutable => new DateTimeImmutable('2030-02-15T12:00:00.000Z'),
        );
    }

    private function baseRevision(TenantContext $tenant, string $artifactKey): ArtifactRevisionReceipt
    {
        $service = new ArtifactRevisionService(new PdoArtifactRevisionRepository($this->pdo));
        $created = $service->createRevision(
            $this->context($tenant, $artifactKey, 'write'),
            self::ARTIFACT_TYPE,
            $artifactKey,
            null,
            null,
            'artifact-base-create-' . hash('sha256', $artifactKey),
        );

        return $service->finalizeRevision(
            $this->context($tenant, $artifactKey, 'write'),
            self::ARTIFACT_TYPE,
            $artifactKey,
            $created->revisionKey,
            $created->artifactRevision,
            $created->revision,
            'record.body',
            '1',
            'payload/' . $artifactKey . '/base',
            hash('sha256', 'base:' . $artifactKey),
            null,
            'artifact-base-finalize-' . hash('sha256', $artifactKey),
        );
    }

    private function context(TenantContext $tenant, string $artifactKey, string $operation): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $tenant,
            self::ARTIFACT_TYPE,
            $operation,
            [new RequestedTargetSet(self::ARTIFACT_TYPE, [$artifactKey])],
            hash('sha256', self::ARTIFACT_TYPE . ':' . $artifactKey . ':' . $operation),
        ));
    }

    /** @return array{int, TenantContext} */
    private function seedContext(string $requestId): array
    {
        $this->pdo->exec('INSERT INTO pa_tenant VALUES ()');
        $tenantId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO pa_tenant_member (id, tenant_id, account_id, status) VALUES (11, ?, 101, 'active')")
            ->execute([$tenantId]);

        return [$tenantId, TenantContext::fromValidatedSession(new ValidatedTenantSession(
            101,
            '01J00000000000000000000000',
            $tenantId,
            101,
            11,
            'admin-web',
            new DateTimeImmutable('2031-01-01T00:00:00.000Z'),
            1,
        ), $requestId)];
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
  UNIQUE KEY uk_collaboration_service_member (tenant_id, id)
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

final readonly class TestCollaborationPolicyProvider implements CollaborationPolicyProvider
{
    public function __construct(
        private PDO $pdo,
        private CollaborationPolicy $policy,
        private string $mode,
    ) {}

    public function connection(): PDO
    {
        return $this->pdo;
    }

    public function policy(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $capability,
        DateTimeImmutable $evaluatedAt,
    ): ?CollaborationPolicy {
        if ($this->mode === 'fail') {
            throw new RuntimeException('provider-secret-must-not-escape');
        }

        return $this->mode === 'deny' ? null : $this->policy;
    }
}

final readonly class TestCollaborationSubmissionProvider implements CollaborationSubmissionProvider
{
    public function __construct(private PDO $pdo) {}

    public function connection(): PDO
    {
        return $this->pdo;
    }

    public function submission(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $sessionKey,
        string $snapshotKey,
        string $snapshotSha256,
        int $latestSequence,
        DateTimeImmutable $evaluatedAt,
    ): ?CollaborationSubmission {
        return new CollaborationSubmission(
            'record.body',
            '1',
            'payload/' . $artifactKey . '/published',
            hash('sha256', $snapshotSha256 . ':' . $latestSequence),
        );
    }
}

final class FailingCollaborationPublisher implements CollaborationRevisionPublisher
{
    public function __construct(private readonly PDO $pdo)
    {
        $pdo->exec('CREATE TABLE collaboration_publish_probe (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) ENGINE=InnoDB');
    }

    public function connection(): PDO
    {
        return $this->pdo;
    }

    public function assertBaseRevision(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $revisionKey,
        string $canonicalEnvelopeSha256,
    ): void {}

    public function publish(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $parentRevisionKey,
        CollaborationSubmission $submission,
        string $idempotencyKey,
    ): array {
        $this->pdo->exec('INSERT INTO collaboration_publish_probe VALUES ()');
        throw new RuntimeException('publisher-secret-must-not-escape');
    }
}
