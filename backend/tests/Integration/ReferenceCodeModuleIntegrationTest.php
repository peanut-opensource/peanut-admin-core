<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\App\referencecode\ReferenceCodeRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Authorization\ModuleAuthorizationCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\ReferenceCodes\Database\Schema as ReferenceCodeSchema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use think\Request;
use think\Response;

final class ReferenceCodeModuleIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_b04_reference_codes_test';
    private const OWNER_MODULE = 'example.reference';
    private const OWNER_SET = 'generic-codes';

    private PDO $admin;
    private PDO $pdo;
    private int $tenantId;
    private int $memberId;
    private int $accountId;
    private CompiledModuleRegistry $modules;
    private string $fixtureRoot;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through the focused B04 Host integration gate.');
        }
        if (getenv('DB_HOST') !== '127.0.0.1') {
            throw new RuntimeException('DB_HOST must be 127.0.0.1.');
        }
        $mysqlPort = $this->requiredPort('MYSQL_PORT');
        $dbPort = $this->requiredPort('DB_PORT');
        if ($mysqlPort !== $dbPort) {
            throw new RuntimeException('Reference Codes MYSQL_PORT and DB_PORT must match.');
        }
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$dbPort};charset=utf8mb4",
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$dbPort};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'AUTH_IDENTIFIER_HMAC_KEY'] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv("DB_PORT={$dbPort}");
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv("DB_PASSWORD={$rootPassword}");
        putenv('AUTH_IDENTIFIER_HMAC_KEY=reference-code-host-integration-key');

        $root = dirname(__DIR__, 3);
        $installationRoot = getenv('PEANUT_B04_INSTALL_ROOT');
        $installationRoot = is_string($installationRoot) && $installationRoot !== ''
            ? $installationRoot
            : $root;
        $installation = (new InstallWorkflow($installationRoot, $this->pdo))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            'reference-code-owner@example.test',
            'Reference-Code-P1-2026!',
            'Reference Code Owner',
            [
                'code' => 'reference-code-host',
                'name' => 'Reference Code Host',
                'owner_email' => 'reference-code-owner@example.test',
                'owner_name' => 'Reference Code Owner',
            ],
        );
        $this->tenantId = (int) $installation['tenant']['tenant_id'];
        $this->memberId = (int) $installation['tenant']['owner_member_id'];
        $this->accountId = (int) $this->scalar(
            'SELECT account_id FROM pa_tenant_member WHERE tenant_id = ? AND id = ?',
            [$this->tenantId, $this->memberId],
        );
        $this->modules = $this->syntheticModules(RuntimeModuleRegistry::compile($root));
        $this->installHostFixture();
        ReferenceCodeRuntimeFactory::synchronizeDefinitions(
            $this->pdo,
            $this->modules,
            new DateTimeImmutable('2026-07-20T00:00:00.000Z'),
        );
        $this->grantPermissions();
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
        foreach ($this->originalEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        if (isset($this->fixtureRoot)) {
            @unlink($this->fixtureRoot . '/reference-code-sets.json');
            @rmdir($this->fixtureRoot);
        }
    }

    public function testCreateCommitsIdentityVersionAuditAndIdempotencyOnOnePdo(): void
    {
        self::assertSame('uk_module_installation_key', $this->queryPlan(<<<'SQL'
EXPLAIN SELECT module_key FROM pa_module_installation
WHERE module_key = :module_key FOR SHARE
SQL, ['module_key' => 'peanut.reference-codes'])['key']);
        self::assertSame('uk_tenant_module', $this->queryPlan(<<<'SQL'
EXPLAIN SELECT tenant_id, module_key FROM pa_tenant_module
WHERE tenant_id = :tenant_id AND module_key = :module_key FOR SHARE
SQL, ['tenant_id' => $this->tenantId, 'module_key' => 'peanut.reference-codes'])['key']);
        $this->pdo->beginTransaction();
        try {
            $lock = new ReflectionMethod(ReferenceCodeRuntimeFactory::class, 'lockModuleAvailability');
            $lock->invoke(null, $this->pdo, $this->tenantId, 'peanut.reference-codes');
            $this->assertModuleUpdateBlocked(<<<'SQL'
UPDATE pa_module_installation SET updated_at = UTC_TIMESTAMP(3)
WHERE module_key = :module_key
SQL, ['module_key' => 'peanut.reference-codes']);
            $this->assertModuleUpdateBlocked(<<<'SQL'
UPDATE pa_tenant_module SET updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = :module_key
SQL, ['tenant_id' => $this->tenantId, 'module_key' => 'peanut.reference-codes']);
        } finally {
            $this->pdo->rollBack();
        }
        $response = $this->create('sample-code', 'reference-create-0001', 'req_reference_create_0001');

        self::assertSame(201, $response->getCode(), json_encode($response->getData(), JSON_THROW_ON_ERROR));
        self::assertSame('"rev-1"', $response->getHeader('ETag'));
        self::assertSame($this->detailPath('sample-code'), $response->getHeader('Location'));
        self::assertSame(1, $this->tableCount('pa_reference_code_entry'));
        self::assertSame(1, $this->tableCount('pa_reference_code_entry_version'));
        self::assertSame(1, $this->tableCount(
            'pa_tenant_audit_event',
            "event_type = 'reference-code.created' AND action = 'peanut.reference-codes.create'",
        ));
        self::assertSame(1, $this->tableCount('pa_tenant_idempotency_record', "operation_key = 'createReferenceCode'"));
    }

    public function testReplaceAppendsOneVersionWithStrongPreconditionAndRedactedAudit(): void
    {
        $this->create('replace-code', 'reference-replace-seed-0001', 'req_reference_replace_seed_0001');
        $response = ReferenceCodeRuntimeFactory::replaceCode(
            $this->request('PUT', $this->detailPath('replace-code'), 'req_reference_replace_0001', [
                'label' => 'Replacement label',
                'metadata' => ['private-value' => 'must-not-be-audited'],
                'status' => 'inactive',
                'sort_order' => 7,
                'effective_at' => '2020-01-01T00:00:00.000Z',
                'expires_at' => null,
            ], [
                'if-match' => '"rev-1"',
                'idempotency-key' => 'reference-replace-0001',
            ]),
            self::OWNER_MODULE,
            self::OWNER_SET,
            'replace-code',
            $this->pdo,
            $this->modules,
        );

        self::assertSame(200, $response->getCode());
        self::assertSame('"rev-2"', $response->getHeader('ETag'));
        self::assertSame(2, $this->tableCount('pa_reference_code_entry_version'));
        $audit = (string) $this->scalar(
            "SELECT metadata_json FROM pa_tenant_audit_event WHERE event_type = 'reference-code.changed'"
                . " AND action = 'peanut.reference-codes.replace'",
        );
        self::assertStringContainsString('changed_fields', $audit);
        self::assertStringContainsString('metadata', $audit);
        self::assertStringNotContainsString('private-value', $audit);
        self::assertStringNotContainsString('must-not-be-audited', $audit);
        self::assertStringNotContainsString('Replacement label', $audit);
    }

    public function testRetireIsTerminalAndCreatesOneInactiveVersion(): void
    {
        $this->create('retire-code', 'reference-retire-seed-0001', 'req_reference_retire_seed_0001');
        $response = ReferenceCodeRuntimeFactory::retireCode(
            $this->request('DELETE', $this->detailPath('retire-code'), 'req_reference_retire_0001', [], [
                'if-match' => '"rev-1"',
                'idempotency-key' => 'reference-retire-0001',
            ]),
            self::OWNER_MODULE,
            self::OWNER_SET,
            'retire-code',
            $this->pdo,
            $this->modules,
        );

        self::assertSame(200, $response->getCode());
        self::assertSame('retired', $response->getData()['data']['lifecycle']);
        self::assertSame('inactive', $response->getData()['data']['effective']['status']);
        self::assertSame(2, $this->tableCount('pa_reference_code_entry_version'));
        self::assertSame(1, $this->tableCount(
            'pa_tenant_audit_event',
            "event_type = 'reference-code.retired' AND action = 'peanut.reference-codes.retire'",
        ));
    }

    public function testExactReplayReturnsStoredSafeReceiptWithoutAnotherMutationOrAudit(): void
    {
        $first = $this->create('replay-code', 'reference-replay-0001', 'req_reference_replay_first');
        $replay = $this->create('replay-code', 'reference-replay-0001', 'req_reference_replay_second');

        self::assertSame(201, $first->getCode());
        self::assertSame($first->getData()['data'], $replay->getData()['data']);
        self::assertSame('req_reference_replay_first', $first->getData()['meta']['request_id']);
        self::assertSame('req_reference_replay_second', $replay->getData()['meta']['request_id']);
        self::assertSame('"rev-1"', $replay->getHeader('ETag'));
        self::assertSame($this->detailPath('replay-code'), $replay->getHeader('Location'));
        self::assertSame(1, $this->tableCount('pa_reference_code_entry'));
        self::assertSame(1, $this->tableCount('pa_reference_code_entry_version'));
        self::assertSame(1, $this->tableCount(
            'pa_tenant_audit_event',
            "event_type = 'reference-code.created' AND action = 'peanut.reference-codes.create'",
        ));
    }

    public function testReusedIdempotencyKeyWithDifferentCanonicalRequestIsConflict(): void
    {
        $this->create('first-code', 'reference-reused-0001', 'req_reference_reused_first');
        $conflict = $this->create('other-code', 'reference-reused-0001', 'req_reference_reused_second');

        self::assertSame(409, $conflict->getCode());
        self::assertSame('IDEMPOTENCY_KEY_REUSED', $conflict->getData()['code']);
        self::assertSame(1, $this->tableCount('pa_reference_code_entry'));
    }

    public function testOwnerDisableIsRecheckedBeforeReplayAndDoesNotAcquireNewState(): void
    {
        $this->create('owner-guard', 'reference-owner-guard-0001', 'req_reference_owner_guard_first');
        $this->disableModule(self::OWNER_MODULE);

        $denied = $this->create('owner-guard', 'reference-owner-guard-0001', 'req_reference_owner_guard_second');

        self::assertSame(404, $denied->getCode());
        self::assertSame('REFERENCE_CODE_SET_NOT_FOUND', $denied->getData()['code']);
        self::assertSame(1, $this->tableCount('pa_tenant_idempotency_record'));
        self::assertSame(1, $this->tableCount(
            'pa_tenant_audit_event',
            "event_type = 'reference-code.created' AND action = 'peanut.reference-codes.create'",
        ));
    }

    public function testHostDisableIsRecheckedBeforeReplayAndReturnsModuleUnavailable(): void
    {
        $this->create('host-guard', 'reference-host-guard-0001', 'req_reference_host_guard_first');
        $this->disableModule('peanut.reference-codes');

        $denied = $this->create('host-guard', 'reference-host-guard-0001', 'req_reference_host_guard_second');

        self::assertSame(404, $denied->getCode());
        self::assertSame('MODULE_UNAVAILABLE', $denied->getData()['code']);
        self::assertSame(1, $this->tableCount('pa_reference_code_entry'));
        self::assertSame(1, $this->tableCount('pa_tenant_idempotency_record'));
    }

    public function testPermissionDenialOccursBeforeIdempotencyAndMutation(): void
    {
        $this->pdo->exec(<<<'SQL'
DELETE role_permission FROM pa_role_permission role_permission
JOIN pa_permission permission ON permission.id = role_permission.permission_id
WHERE permission.`key` = 'peanut.reference-codes.manage'
SQL);
        $denied = $this->create('permission-code', 'reference-permission-0001', 'req_reference_permission_0001');

        self::assertSame(403, $denied->getCode());
        self::assertSame('AUTHZ_PERMISSION_DENIED', $denied->getData()['code']);
        self::assertSame(0, $this->tableCount('pa_reference_code_entry'));
        self::assertSame(0, $this->tableCount('pa_tenant_idempotency_record'));
    }

    public function testReadEndpointsUseOneAsOfAndExactResponseShapesWithoutAudit(): void
    {
        $this->create('read-code', 'reference-read-seed-0001', 'req_reference_read_seed_0001');
        $auditCount = $this->tableCount('pa_tenant_audit_event');
        $sets = ReferenceCodeRuntimeFactory::listSets($this->request(
            'GET',
            '/api/v1/reference-code-sets',
            'req_reference_sets_0001',
        ), $this->pdo, $this->modules);
        $list = ReferenceCodeRuntimeFactory::listCodes(
            $this->request('GET', $this->collectionPath(), 'req_reference_list_0001', query: [
                'as_of' => '2099-07-20T00:00:00.000Z',
                'effective_status' => 'all',
                'include_retired' => 'false',
                'page' => '1',
                'page_size' => '50',
            ]),
            self::OWNER_MODULE,
            self::OWNER_SET,
            $this->pdo,
            $this->modules,
        );
        $detail = ReferenceCodeRuntimeFactory::getCode(
            $this->request('GET', $this->detailPath('read-code'), 'req_reference_detail_0001', query: [
                'as_of' => '2099-07-20T00:00:00.000Z',
            ]),
            self::OWNER_MODULE,
            self::OWNER_SET,
            'read-code',
            $this->pdo,
            $this->modules,
        );

        self::assertSame(200, $sets->getCode());
        self::assertSame(['data', 'meta'], array_keys($sets->getData()));
        self::assertSame(['items'], array_keys($sets->getData()['data']));
        self::assertSame('req_reference_sets_0001', $sets->getData()['meta']['request_id']);
        self::assertSame(200, $list->getCode());
        self::assertSame(['data', 'meta'], array_keys($list->getData()));
        self::assertSame(['items', 'as_of', 'page', 'page_size', 'total'], array_keys($list->getData()['data']));
        self::assertSame('req_reference_list_0001', $list->getData()['meta']['request_id']);
        self::assertSame(200, $detail->getCode());
        self::assertSame(['data', 'meta'], array_keys($detail->getData()));
        self::assertSame('req_reference_detail_0001', $detail->getData()['meta']['request_id']);
        self::assertSame('"rev-1"', $detail->getHeader('ETag'));
        self::assertSame($auditCount, $this->tableCount('pa_tenant_audit_event'));
    }

    public function testIdempotencyCompletionFailureRollsBackEntryVersionAndAudit(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TRIGGER fail_reference_idempotency_completion
BEFORE UPDATE ON pa_tenant_idempotency_record
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced reference idempotency completion failure'
SQL);
        $response = $this->create('rollback-code', 'reference-rollback-0001', 'req_reference_rollback_0001');

        self::assertSame(500, $response->getCode());
        self::assertSame('INTERNAL_ERROR', $response->getData()['code']);
        self::assertStringNotContainsString('forced reference', json_encode($response->getData(), JSON_THROW_ON_ERROR));
        self::assertSame(0, $this->tableCount('pa_reference_code_entry'));
        self::assertSame(0, $this->tableCount('pa_reference_code_entry_version'));
        self::assertSame(0, $this->tableCount('pa_tenant_audit_event', "event_type LIKE 'reference-code.%'"));
        self::assertSame(0, $this->tableCount('pa_tenant_idempotency_record'));
    }

    private function create(string $code, string $key, string $requestId): Response
    {
        return ReferenceCodeRuntimeFactory::createCode(
            $this->request('POST', $this->collectionPath(), $requestId, [
                'code' => $code,
                'label' => 'Synthetic label',
                'metadata' => ['marker' => true],
                'status' => 'active',
                'sort_order' => 0,
                'effective_at' => '2020-01-01T00:00:00.000Z',
                'expires_at' => null,
            ], [
                'if-none-match' => '*',
                'idempotency-key' => $key,
            ]),
            self::OWNER_MODULE,
            self::OWNER_SET,
            $this->pdo,
            $this->modules,
        );
    }

    private function syntheticModules(CompiledModuleRegistry $compiled): CompiledModuleRegistry
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/peanut-reference-code-host-' . bin2hex(random_bytes(8));
        if (!mkdir($this->fixtureRoot, 0700) && !is_dir($this->fixtureRoot)) {
            throw new RuntimeException('Could not create the synthetic reference-code Module fixture.');
        }
        $fixtureRoot = realpath($this->fixtureRoot);
        if (!is_string($fixtureRoot)) {
            throw new RuntimeException('Could not normalize the synthetic reference-code Module fixture.');
        }
        $this->fixtureRoot = $fixtureRoot;
        file_put_contents($this->fixtureRoot . '/reference-code-sets.json', json_encode([[
            'key' => self::OWNER_SET,
            'name' => 'Generic codes',
            'description' => 'Synthetic Host integration declaration.',
        ]], JSON_THROW_ON_ERROR));

        $host = (new ManifestLoader())->load(
            dirname(__DIR__, 3) . '/backend/app/Modules/Peanut/ReferenceCodes',
        );
        $hostPresent = false;
        $modules = array_map(function (ManifestDocument $module) use ($host, &$hostPresent): ManifestDocument {
            if (($module->data['key'] ?? null) === 'peanut.reference-codes') {
                $hostPresent = true;

                return $host;
            }
            if (($module->data['key'] ?? null) !== self::OWNER_MODULE) {
                return $module;
            }
            $data = $module->data;
            $backend = is_array($data['backend'] ?? null) ? $data['backend'] : [];
            $data['backend'] = [...$backend, 'reference_code_sets' => 'reference-code-sets.json'];

            return ManifestDocument::fromArray($this->fixtureRoot, $data);
        }, $compiled->modules);
        if (!$hostPresent) {
            array_unshift($modules, $host);
        }

        $ownedTables = $compiled->ownedTableOwners;
        foreach (ReferenceCodeSchema::tableNames() as $table) {
            $ownedTables[$table] = 'peanut.reference-codes';
        }

        return new CompiledModuleRegistry(
            $modules,
            $compiled->targetTypeOwners,
            $ownedTables,
            $compiled->menus,
            hash('sha256', $compiled->revision . '|' . $host->digest),
        );
    }

    private function installHostFixture(): void
    {
        foreach (ReferenceCodeSchema::tableNames() as $table) {
            if (!$this->tableExists($table)) {
                $this->pdo->exec(ReferenceCodeSchema::createSql($table));
            }
        }
        $host = null;
        foreach ($this->modules->modules as $module) {
            if (($module->data['key'] ?? null) === 'peanut.reference-codes') {
                $host = $module;
                break;
            }
        }
        if (!$host instanceof ManifestDocument) {
            throw new RuntimeException('The synthetic registry is missing the reference-code Host Module.');
        }
        $now = '2026-07-20 00:00:00.000';
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (
    module_key, installed_version, manifest_schema_version, manifest_digest,
    status, revision, installed_at, activated_at, created_at, updated_at
) VALUES (
    'peanut.reference-codes', '0.1.0', 1, :manifest_digest,
    'active', 1, :installed_at, :activated_at, :created_at, :updated_at
)
ON DUPLICATE KEY UPDATE
    installed_version = VALUES(installed_version), manifest_digest = VALUES(manifest_digest),
    status = 'active', revision = revision + 1, activated_at = VALUES(activated_at),
    updated_at = VALUES(updated_at)
SQL);
        $statement->execute([
            'manifest_digest' => $host->digest,
            'installed_at' => $now,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        (new ModuleAuthorizationCatalogSynchronizer(
            new PdoAuthorizationCatalogRepository($this->pdo),
        ))->synchronize($this->modules);
        if ((int) $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_module
WHERE tenant_id = ? AND module_key = 'peanut.reference-codes'
SQL, [$this->tenantId]) === 0) {
            (new PdoModuleRuntimeRepository($this->pdo))->enable(
                $this->tenantId,
                'peanut.reference-codes',
                [],
                new DateTimeImmutable('2026-07-20T00:00:00.000Z'),
                'manual',
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    private function request(
        string $method,
        string $url,
        string $requestId,
        array $body = [],
        array $headers = [],
        array $query = [],
    ): Request {
        $request = (new Request())
            ->setMethod($method)
            ->setUrl($url)
            ->withServer([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $url,
                'HTTP_HOST' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->withGet($query)
            ->withHeader([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'x-request-id' => $requestId,
                ...$headers,
            ])
            ->withPost($body)
            ->withInput(json_encode($body, JSON_THROW_ON_ERROR));

        return $request->withRoute(['tenant_context' => TenantContext::fromValidatedSession(
            new ValidatedTenantSession(
                1,
                'reference-code-session',
                $this->tenantId,
                $this->accountId,
                $this->memberId,
                'admin-web',
                new DateTimeImmutable('2026-07-20T00:00:00.000Z'),
                1,
            ),
            $requestId,
        )]);
    }

    private function grantPermissions(): void
    {
        $this->pdo->exec(<<<SQL
INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_by_member_id, granted_at)
SELECT {$this->tenantId}, role.id, permission.id, {$this->memberId}, UTC_TIMESTAMP(3)
FROM pa_role role
JOIN pa_permission permission ON permission.`key` IN (
    'peanut.reference-codes.read',
    'peanut.reference-codes.manage'
)
WHERE role.tenant_id = {$this->tenantId} AND role.`key` = 'core.tenant-owner'
ON DUPLICATE KEY UPDATE granted_at = VALUES(granted_at)
SQL);
    }

    private function disableModule(string $moduleKey): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_module SET status = 'disabled', updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = :module_key
SQL);
        $statement->execute(['tenant_id' => $this->tenantId, 'module_key' => $moduleKey]);
    }

    private function collectionPath(): string
    {
        return '/api/v1/reference-code-sets/' . self::OWNER_MODULE . '/' . self::OWNER_SET . '/codes';
    }

    private function detailPath(string $code): string
    {
        return $this->collectionPath() . '/' . $code;
    }

    private function tableCount(string $table, string $where = '1 = 1'): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = :table_name
SQL);
        $statement->execute(['table_name' => $table]);

        return (int) $statement->fetchColumn() === 1;
    }

    /** @param list<mixed> $parameters */
    private function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    /** @param array<string, int|string> $parameters
     * @return array<string, mixed>
     */
    private function queryPlan(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $plan = $statement->fetch();
        self::assertIsArray($plan);

        return $plan;
    }

    /** @param array<string, int|string> $parameters */
    private function assertModuleUpdateBlocked(string $sql, array $parameters): void
    {
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $contender = new PDO(
            'mysql:host=127.0.0.1;port=' . $this->requiredPort('DB_PORT') . ';dbname=' . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $statement = $contender->prepare($sql);
        try {
            $statement->execute($parameters);
            self::fail('Module availability update was not blocked by the command guard lock.');
        } catch (PDOException $exception) {
            self::assertSame('HY000', $exception->getCode());
            self::assertSame(1205, $exception->errorInfo[1] ?? null);
        }
    }

    private function requiredPort(string $name): int
    {
        $value = getenv($name);
        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new RuntimeException("Missing required environment variable: {$name}.");
        }
        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException("Invalid port in environment variable: {$name}.");
        }

        return $port;
    }
}
