<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\controller\api\platform\v1\PlatformSettingsController;
use PeanutAdmin\App\controller\api\v1\SettingsController;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedPlatformSession;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

final class SettingsModuleIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_b03_host_test';

    private PDO $admin;
    private PDO $pdo;
    private int $tenantId;
    private int $memberId;
    private int $accountId;
    private int $operatorId;
    private int $platformAccountId;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through the focused B03 Host integration gate.');
        }
        $this->requiredPort('MYSQL_PORT');
        $port = $this->requiredPort('DB_PORT');
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        foreach ([
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'AUTH_IDENTIFIER_HMAC_KEY',
            'PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID',
            'PEANUT_SETTINGS_SECRET_KEYS',
        ] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv("DB_PORT={$port}");
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv("DB_PASSWORD={$rootPassword}");
        putenv('AUTH_IDENTIFIER_HMAC_KEY=settings-host-integration-hmac-key');
        putenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID=integration');
        putenv('PEANUT_SETTINGS_SECRET_KEYS=' . json_encode([
            'integration' => base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)),
        ], JSON_THROW_ON_ERROR));

        $root = dirname(__DIR__, 3);
        $installation = (new InstallWorkflow($root, $this->pdo))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            'settings-owner@example.test',
            'Settings-Host-P1-2026!',
            'Settings Owner',
            [
                'code' => 'settings-host',
                'name' => 'Settings Host',
                'owner_email' => 'settings-owner@example.test',
                'owner_name' => 'Settings Owner',
            ],
        );
        $this->tenantId = (int) $installation['tenant']['tenant_id'];
        $this->memberId = (int) $installation['tenant']['owner_member_id'];
        $this->operatorId = (int) $installation['platform']['operator_id'];
        $this->platformAccountId = (int) $installation['platform']['account_id'];
        $this->accountId = (int) $this->scalar(<<<'SQL'
SELECT account_id FROM pa_tenant_member WHERE tenant_id = ? AND id = ?
SQL, [$this->tenantId, $this->memberId]);
        SettingsRuntimeFactory::synchronizeDefinitions(
            $this->pdo,
            RuntimeModuleRegistry::compile($root),
            new DateTimeImmutable('2026-07-19T00:00:00Z'),
        );
        $this->grantTenantPermissions();
        $this->grantPlatformPermissions();
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
        foreach ($this->originalEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
    }

    public function testTenantReplaceCommitsValueAuditAndIdempotencyTogether(): void
    {
        $requestId = 'req_settings_tenant_replace_0001';
        $response = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                $requestId,
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-tenant-replace-0001',
                ],
            ),
            'example.target',
            'display-density',
        );

        self::assertSame(200, $response->getCode());
        self::assertSame('"rev-1"', $response->getHeader('ETag'));
        self::assertSame('compact', $response->getData()['data']['value']);
        self::assertSame('tenant', $response->getData()['data']['source_scope']);
        self::assertSame('1', $response->getData()['data']['revision']);
        self::assertSame($requestId, $response->getData()['meta']['request_id']);

        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM pa_setting_tenant_value'));
        self::assertSame(1, (int) $this->scalar(
            'SELECT COUNT(*) FROM pa_tenant_audit_event WHERE request_id = ?',
            [$requestId],
        ));
        self::assertSame('completed', $this->scalar(
            'SELECT status FROM pa_tenant_idempotency_record WHERE operation_key = ?',
            ['replaceTenantSetting'],
        ));

        $metadata = json_decode((string) $this->scalar(
            'SELECT metadata_json FROM pa_tenant_audit_event WHERE request_id = ?',
            [$requestId],
        ), true, 32, JSON_THROW_ON_ERROR);
        self::assertEquals([
            'module_key' => 'example.target',
            'setting_key' => 'display-density',
            'scope' => 'tenant',
            'changed_fields' => 'value',
            'revision' => '1',
        ], $metadata);
        self::assertStringNotContainsString('compact', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function testTenantListAndUnsetReturnStrongEtagsAndRedactedParserRecords(): void
    {
        $created = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_tenant_seed_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-tenant-seed-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $created->getCode());

        $list = (new SettingsController())->listTenantSettings($this->request(
            'GET',
            '/api/v1/settings',
            'req_settings_tenant_list_0001',
        ));
        self::assertSame(200, $list->getCode());
        self::assertMatchesRegularExpression('/^"settings-[a-f0-9]{64}"$/', $list->getHeader('ETag'));
        $items = $list->getData()['data']['items'];
        self::assertSame([
            'example.target:display-density',
            'example.target:fixture-secret',
        ], array_map(
            static fn(array $item): string => $item['module_key'] . ':' . $item['setting_key'],
            $items,
        ));
        self::assertSame('compact', $items[0]['value']);
        self::assertArrayNotHasKey('value', $items[1]);
        self::assertFalse($items[1]['configured']);

        $unset = (new SettingsController())->unsetTenantSetting(
            $this->request(
                'DELETE',
                '/api/v1/settings/example.target/display-density',
                'req_settings_tenant_unset_0001',
                [],
                [
                    'if-match' => '"rev-1"',
                    'idempotency-key' => 'settings-tenant-unset-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $unset->getCode());
        self::assertSame('"rev-2"', $unset->getHeader('ETag'));
        self::assertFalse($unset->getData()['data']['configured']);
        self::assertSame('2', $unset->getData()['data']['revision']);
        self::assertSame('completed', $this->scalar(
            'SELECT status FROM pa_tenant_idempotency_record WHERE operation_key = ?',
            ['unsetTenantSetting'],
        ));

        $afterUnset = (new SettingsController())->listTenantSettings($this->request(
            'GET',
            '/api/v1/settings',
            'req_settings_tenant_after_unset_0001',
        ));
        self::assertSame(200, $afterUnset->getCode());
        self::assertSame('default', $afterUnset->getData()['data']['items'][0]['source_scope']);
        self::assertSame('2', $afterUnset->getData()['data']['items'][0]['revision']);
        self::assertSame('"rev-2"', $afterUnset->getData()['data']['items'][0]['etag']);

        $replaced = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_tenant_after_unset_replace_0001',
                ['value' => 'comfortable'],
                [
                    'if-match' => '"rev-2"',
                    'idempotency-key' => 'settings-after-unset-replace-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $replaced->getCode());
        self::assertSame('"rev-3"', $replaced->getHeader('ETag'));
    }

    public function testPlatformReplaceUnsetAndReplayUseTheAtomicPlatformChain(): void
    {
        $controller = new PlatformSettingsController();
        $create = $controller->replaceDeploymentSetting(
            $this->platformRequest(
                'PUT',
                '/api/platform/v1/settings/example.target/display-density',
                'req_settings_platform_replace_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-platform-replace-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $create->getCode());
        self::assertSame('deployment', $create->getData()['data']['source_scope']);
        self::assertSame('"rev-1"', $create->getHeader('ETag'));

        $replay = $controller->replaceDeploymentSetting(
            $this->platformRequest(
                'PUT',
                '/api/platform/v1/settings/example.target/display-density',
                'req_settings_platform_replay_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-platform-replace-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $replay->getCode());
        self::assertSame(1, (int) $this->scalar('SELECT revision FROM pa_setting_deployment_value'));
        self::assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM pa_platform_audit_event WHERE event_type = 'setting.deployment.replaced'",
        ));

        $conflict = $controller->replaceDeploymentSetting(
            $this->platformRequest(
                'PUT',
                '/api/platform/v1/settings/example.target/display-density',
                'req_settings_platform_conflict_0001',
                ['value' => 'comfortable'],
                [
                    'if-match' => '"rev-1"',
                    'idempotency-key' => 'settings-platform-replace-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(409, $conflict->getCode());
        self::assertSame('IDEMPOTENCY_KEY_REUSED', $conflict->getData()['code']);

        $unset = $controller->unsetDeploymentSetting(
            $this->platformRequest(
                'DELETE',
                '/api/platform/v1/settings/example.target/display-density',
                'req_settings_platform_unset_0001',
                [],
                [
                    'if-match' => '"rev-1"',
                    'idempotency-key' => 'settings-platform-unset-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $unset->getCode());
        self::assertFalse($unset->getData()['data']['configured']);
        self::assertSame('"rev-2"', $unset->getHeader('ETag'));
        self::assertSame('completed', $this->scalar(
            'SELECT status FROM pa_platform_idempotency_record WHERE operation_key = ?',
            ['unsetDeploymentSetting'],
        ));
    }

    public function testDeploymentListUsesThePackageReadApiAndStrongCollectionEtag(): void
    {
        $response = (new PlatformSettingsController())->listDeploymentSettings($this->platformRequest(
            'GET',
            '/api/platform/v1/settings',
            'req_settings_platform_list_0001',
        ));

        self::assertSame(200, $response->getCode());
        self::assertMatchesRegularExpression('/^"settings-[a-f0-9]{64}"$/', $response->getHeader('ETag'));
        self::assertSame([[
            'module_key' => 'example.target',
            'setting_key' => 'display-density',
            'name' => 'Display density',
            'description' => 'Fictional scalar setting used to verify Module-owned definitions',
            'schema' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'string',
                'enum' => ['compact', 'comfortable'],
            ],
            'required' => false,
            'secret' => false,
            'configured' => false,
            'source_scope' => 'default',
            'value' => 'comfortable',
            'effective_at' => null,
            'expires_at' => null,
            'revision' => '1',
            'etag' => null,
        ]], $response->getData()['data']['items']);
    }

    public function testExactReplayRechecksTheCurrentDefinitionOwnerAvailability(): void
    {
        $controller = new SettingsController();
        $create = $controller->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_owner_replay_create_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-owner-replay-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $create->getCode());

        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_module SET status = 'disabled', updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = 'example.target'
SQL);
        $statement->execute(['tenant_id' => $this->tenantId]);

        $replay = $controller->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_owner_replay_retry_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-owner-replay-0001',
                ],
            ),
            'example.target',
            'display-density',
        );

        self::assertSame(404, $replay->getCode());
        self::assertSame('SETTING_NOT_FOUND', $replay->getData()['code']);
        self::assertSame(1, (int) $this->scalar('SELECT revision FROM pa_setting_tenant_value'));
        self::assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM pa_tenant_audit_event WHERE event_type = 'setting.tenant.replaced'",
        ));
    }

    public function testExactReplayRechecksTheSettingsHostModuleAvailability(): void
    {
        $controller = new SettingsController();
        $create = $controller->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_host_replay_create_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-host-replay-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $create->getCode());

        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_module SET status = 'disabled', updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = 'peanut.settings'
SQL);
        $statement->execute(['tenant_id' => $this->tenantId]);

        $replay = $controller->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_host_replay_retry_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-host-replay-0001',
                ],
            ),
            'example.target',
            'display-density',
        );

        self::assertSame(404, $replay->getCode());
        self::assertSame('MODULE_UNAVAILABLE', $replay->getData()['code']);
        self::assertSame(1, (int) $this->scalar('SELECT revision FROM pa_setting_tenant_value'));
        self::assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM pa_tenant_audit_event WHERE event_type = 'setting.tenant.replaced'",
        ));
    }

    public function testRealSettingsCommandHoldsGuardLocksThroughNewMutation(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE pa_settings_test_barrier (
    barrier_key VARCHAR(64) PRIMARY KEY
) ENGINE=MyISAM
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TRIGGER pa_settings_test_pause_insert
BEFORE INSERT ON pa_setting_tenant_value
FOR EACH ROW
BEGIN
    INSERT INTO pa_settings_test_barrier (barrier_key)
    VALUES ('new-mutation')
    ON DUPLICATE KEY UPDATE barrier_key = VALUES(barrier_key);
    DO SLEEP(5);
END
SQL);

        $worker = $this->startTenantReplaceWorker(
            'req_settings_guard_new_mutation_0001',
            'settings-guard-new-mutation-0001',
        );
        try {
            $this->waitUntil(static function (PDO $pdo): bool {
                $statement = $pdo->query(
                    "SELECT COUNT(*) FROM pa_settings_test_barrier WHERE barrier_key = 'new-mutation'",
                );

                return $statement !== false && (int) $statement->fetchColumn() === 1;
            }, 'The real Settings command did not reach the guarded mutation.');
            $this->assertSettingsGuardLocksHeld();
            $result = $this->finishTenantReplaceWorker($worker);
            $worker = null;
        } finally {
            if ($worker !== null) {
                $this->stopTenantReplaceWorker($worker);
            }
            $this->pdo->exec('DROP TRIGGER IF EXISTS pa_settings_test_pause_insert');
            $this->pdo->exec('DROP TABLE IF EXISTS pa_settings_test_barrier');
        }

        self::assertSame(200, $result['status'] ?? null);
        self::assertSame('1', $result['body']['data']['revision'] ?? null);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM pa_setting_tenant_value'));
        self::assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM pa_tenant_audit_event WHERE event_type = 'setting.tenant.replaced'",
        ));
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM pa_tenant_idempotency_record'));
    }

    public function testRealSettingsReplayHoldsGuardLocksThroughReplayDecision(): void
    {
        $idempotencyKey = 'settings-guard-replay-0001';
        $created = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_guard_replay_seed_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => $idempotencyKey,
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(200, $created->getCode());

        $blocker = $this->additionalConnection();
        $blocker->beginTransaction();
        $statement = $blocker->query(<<<'SQL'
SELECT id FROM pa_tenant_idempotency_record
WHERE operation_key = 'replaceTenantSetting'
FOR UPDATE
SQL);
        self::assertNotFalse($statement);
        self::assertNotFalse($statement->fetchColumn());

        $worker = $this->startTenantReplaceWorker(
            'req_settings_guard_replay_attempt_0001',
            $idempotencyKey,
        );
        try {
            $this->waitUntil(static function (PDO $pdo): bool {
                $statement = $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM performance_schema.data_lock_waits waits
JOIN performance_schema.data_locks requested
  ON requested.ENGINE_LOCK_ID = waits.REQUESTING_ENGINE_LOCK_ID
WHERE requested.OBJECT_SCHEMA = DATABASE()
  AND requested.OBJECT_NAME = 'pa_tenant_idempotency_record'
SQL);

                return $statement !== false && (int) $statement->fetchColumn() >= 1;
            }, 'The real Settings replay did not wait at the idempotency decision.');
            $this->assertSettingsGuardLocksHeld();
            $blocker->commit();
            $result = $this->finishTenantReplaceWorker($worker);
            $worker = null;
        } finally {
            if ($blocker->inTransaction()) {
                $blocker->rollBack();
            }
            if ($worker !== null) {
                $this->stopTenantReplaceWorker($worker);
            }
        }

        self::assertSame(200, $result['status'] ?? null);
        self::assertEquals($created->getData(), $result['body'] ?? null);
        self::assertSame(1, (int) $this->scalar('SELECT revision FROM pa_setting_tenant_value'));
        self::assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM pa_tenant_audit_event WHERE event_type = 'setting.tenant.replaced'",
        ));
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM pa_tenant_idempotency_record'));
    }

    public function testOwnerModuleAndPermissionFailuresDoNotMutateOrAcquireIdempotency(): void
    {
        $this->pdo->exec(<<<SQL
DELETE role_permission FROM pa_role_permission role_permission
JOIN pa_permission permission ON permission.id = role_permission.permission_id
WHERE role_permission.tenant_id = {$this->tenantId}
  AND permission.`key` = 'peanut.settings.manage'
SQL);
        $denied = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_permission_denied_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-permission-denied-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(403, $denied->getCode());
        self::assertSame('AUTHZ_PERMISSION_DENIED', $denied->getData()['code']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_setting_tenant_value'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_tenant_idempotency_record'));

        $this->grantTenantPermissions();
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_module SET status = 'disabled', updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = 'example.target'
SQL);
        $statement->execute(['tenant_id' => $this->tenantId]);
        $ownerUnavailable = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_owner_disabled_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-owner-disabled-0001',
                ],
            ),
            'example.target',
            'display-density',
        );
        self::assertSame(404, $ownerUnavailable->getCode());
        self::assertSame('SETTING_NOT_FOUND', $ownerUnavailable->getData()['code']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_setting_tenant_value'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_tenant_idempotency_record'));

        $list = (new SettingsController())->listTenantSettings($this->request(
            'GET',
            '/api/v1/settings',
            'req_settings_owner_disabled_list_0001',
        ));
        self::assertSame(200, $list->getCode());
        self::assertSame([], $list->getData()['data']['items']);
    }

    public function testSecretWriteNeverReturnsOrAuditsPlaintext(): void
    {
        $secret = 'host-secret-must-never-leak';
        $requestId = 'req_settings_secret_replace_0001';
        $response = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/fixture-secret',
                $requestId,
                ['value' => $secret],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-secret-replace-0001',
                ],
            ),
            'example.target',
            'fixture-secret',
        );

        self::assertSame(200, $response->getCode());
        self::assertTrue($response->getData()['data']['configured']);
        self::assertArrayNotHasKey('value', $response->getData()['data']);
        self::assertStringNotContainsString($secret, json_encode($response->getData(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($secret, (string) $this->scalar(
            'SELECT metadata_json FROM pa_tenant_audit_event WHERE request_id = ?',
            [$requestId],
        ));
        self::assertStringNotContainsString($secret, (string) $this->scalar(
            'SELECT response_body_json FROM pa_tenant_idempotency_record WHERE operation_key = ?',
            ['replaceTenantSetting'],
        ));
        self::assertNull($this->scalar('SELECT value_json FROM pa_setting_tenant_value'));
        self::assertNotSame($secret, $this->scalar('SELECT ciphertext FROM pa_setting_tenant_value'));
    }

    public function testIdempotencyCompletionFailureRollsBackValueAuditAndLease(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TRIGGER fail_settings_idempotency_completion
BEFORE UPDATE ON pa_tenant_idempotency_record
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced completion failure';
    END IF;
END
SQL);
        $response = (new SettingsController())->replaceTenantSetting(
            $this->request(
                'PUT',
                '/api/v1/settings/example.target/display-density',
                'req_settings_completion_failure_0001',
                ['value' => 'compact'],
                [
                    'if-none-match' => '*',
                    'idempotency-key' => 'settings-completion-failure-0001',
                ],
            ),
            'example.target',
            'display-density',
        );

        self::assertSame(500, $response->getCode());
        self::assertSame('INTERNAL_ERROR', $response->getData()['code']);
        self::assertStringNotContainsString('forced completion failure', json_encode(
            $response->getData(),
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_setting_tenant_value'));
        self::assertSame(0, (int) $this->scalar(
            'SELECT COUNT(*) FROM pa_tenant_audit_event WHERE request_id = ?',
            ['req_settings_completion_failure_0001'],
        ));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_tenant_idempotency_record'));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        string $url,
        string $requestId,
        array $body = [],
        array $headers = [],
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
                'settings-session',
                $this->tenantId,
                $this->accountId,
                $this->memberId,
                'admin-web',
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
                1,
            ),
            $requestId,
        )]);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function platformRequest(
        string $method,
        string $url,
        string $requestId,
        array $body = [],
        array $headers = [],
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
            ->withHeader([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'x-request-id' => $requestId,
                ...$headers,
            ])
            ->withPost($body)
            ->withInput(json_encode($body, JSON_THROW_ON_ERROR));

        return $request->withRoute(['platform_context' => PlatformContext::fromValidatedSession(
            new ValidatedPlatformSession(
                1,
                'settings-platform-session',
                $this->platformAccountId,
                $this->operatorId,
                'platform-web',
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
            ),
            $requestId,
        )]);
    }

    /** @return array{process: resource, stdout: resource, stderr: resource} */
    private function startTenantReplaceWorker(string $requestId, string $idempotencyKey): array
    {
        $root = dirname(__DIR__, 3);
        $environment = getenv();
        $environment['PEANUT_SETTINGS_WORKER_ROOT'] = $root;
        $environment['PEANUT_SETTINGS_WORKER_REQUEST_ID'] = $requestId;
        $environment['PEANUT_SETTINGS_WORKER_IDEMPOTENCY_KEY'] = $idempotencyKey;
        $environment['PEANUT_SETTINGS_WORKER_TENANT_ID'] = (string) $this->tenantId;
        $environment['PEANUT_SETTINGS_WORKER_ACCOUNT_ID'] = (string) $this->accountId;
        $environment['PEANUT_SETTINGS_WORKER_MEMBER_ID'] = (string) $this->memberId;

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $this->tenantReplaceWorkerSource()],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
            $environment,
        );
        if (!is_resource($process)) {
            self::fail('The Settings command worker could not be started.');
        }

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param array{process: resource, stdout: resource, stderr: resource} $worker
     * @return array<string, mixed>
     */
    private function finishTenantReplaceWorker(array $worker): array
    {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        self::assertSame(0, $exitCode, (string) $stderr);
        $decoded = json_decode((string) $stdout, true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @param array{process: resource, stdout: resource, stderr: resource} $worker */
    private function stopTenantReplaceWorker(array $worker): void
    {
        $status = proc_get_status($worker['process']);
        if ($status['running']) {
            proc_terminate($worker['process']);
        }
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        proc_close($worker['process']);
    }

    /** @param callable(PDO): bool $condition */
    private function waitUntil(callable $condition, string $failure): void
    {
        for ($attempt = 0; $attempt < 400; ++$attempt) {
            if ($condition($this->pdo)) {
                return;
            }
            usleep(25_000);
        }

        self::fail($failure);
    }

    private function assertSettingsGuardLocksHeld(): void
    {
        $contender = $this->additionalConnection();
        $writeLocks = [
            'Host deployment' => "SELECT id FROM pa_module_installation WHERE module_key = 'peanut.settings' FOR UPDATE NOWAIT",
            'Host Tenant' => "SELECT id FROM pa_tenant_module "
                . "WHERE tenant_id = {$this->tenantId} AND module_key = 'peanut.settings' FOR UPDATE NOWAIT",
            'owner deployment' => "SELECT id FROM pa_module_installation WHERE module_key = 'example.target' FOR UPDATE NOWAIT",
            'owner Tenant' => "SELECT id FROM pa_tenant_module "
                . "WHERE tenant_id = {$this->tenantId} AND module_key = 'example.target' FOR UPDATE NOWAIT",
            'definition' => "SELECT id FROM pa_setting_definition "
                . "WHERE module_key = 'example.target' AND setting_key = 'display-density' FOR UPDATE NOWAIT",
        ];

        foreach ($writeLocks as $label => $sql) {
            try {
                $contender->query($sql);
                self::fail("The {$label} write lock crossed the Settings command guard.");
            } catch (PDOException $exception) {
                self::assertSame(3572, (int) ($exception->errorInfo[1] ?? 0), $label);
            }
        }
    }

    private function tenantReplaceWorkerSource(): string
    {
        return <<<'PHP'
$required = static function (string $name): string {
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Missing worker environment: {$name}.");
    }

    return $value;
};
$root = $required('PEANUT_SETTINGS_WORKER_ROOT');
require $root . '/vendor/autoload.php';
$requestId = $required('PEANUT_SETTINGS_WORKER_REQUEST_ID');
$request = (new \think\Request())
    ->setMethod('PUT')
    ->setUrl('/api/v1/settings/example.target/display-density')
    ->withServer([
        'REQUEST_METHOD' => 'PUT',
        'REQUEST_URI' => '/api/v1/settings/example.target/display-density',
        'HTTP_HOST' => 'localhost',
        'REMOTE_ADDR' => '127.0.0.1',
    ])
    ->withHeader([
        'accept' => 'application/json',
        'content-type' => 'application/json',
        'x-request-id' => $requestId,
        'if-none-match' => '*',
        'idempotency-key' => $required('PEANUT_SETTINGS_WORKER_IDEMPOTENCY_KEY'),
    ])
    ->withPost(['value' => 'compact'])
    ->withInput(json_encode(['value' => 'compact'], JSON_THROW_ON_ERROR));
$request = $request->withRoute([
    'tenant_context' => \PeanutAdmin\Kernel\Auth\TenantContext::fromValidatedSession(
        new \PeanutAdmin\Kernel\Auth\ValidatedTenantSession(
            1,
            'settings-worker-session',
            (int) $required('PEANUT_SETTINGS_WORKER_TENANT_ID'),
            (int) $required('PEANUT_SETTINGS_WORKER_ACCOUNT_ID'),
            (int) $required('PEANUT_SETTINGS_WORKER_MEMBER_ID'),
            'admin-web',
            new \DateTimeImmutable('2026-07-19T00:00:00Z'),
            1,
        ),
        $requestId,
    ),
]);
$response = (new \PeanutAdmin\App\controller\api\v1\SettingsController())->replaceTenantSetting(
    $request,
    'example.target',
    'display-density',
);
echo json_encode([
    'status' => $response->getCode(),
    'body' => $response->getData(),
], JSON_THROW_ON_ERROR);
PHP;
    }

    private function grantTenantPermissions(): void
    {
        $this->pdo->exec(<<<SQL
INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_by_member_id, granted_at)
SELECT {$this->tenantId}, role.id, permission.id, {$this->memberId}, UTC_TIMESTAMP(3)
FROM pa_role role
JOIN pa_permission permission ON permission.`key` IN ('peanut.settings.read', 'peanut.settings.manage')
WHERE role.tenant_id = {$this->tenantId} AND role.`key` = 'core.tenant-owner'
ON DUPLICATE KEY UPDATE granted_at = VALUES(granted_at)
SQL);
    }

    private function additionalConnection(): PDO
    {
        $port = $this->requiredPort('DB_PORT');
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';

        return new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    private function requiredPort(string $name): int
    {
        $value = getenv($name);
        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new \RuntimeException("Missing required environment variable: {$name}.");
        }
        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException("Invalid port in environment variable: {$name}.");
        }

        return $port;
    }

    private function grantPlatformPermissions(): void
    {
        $this->pdo->exec(<<<SQL
INSERT INTO pa_platform_role_permission (platform_role_id, permission_id, granted_at)
SELECT role.id, permission.id, UTC_TIMESTAMP(3)
FROM pa_platform_role role
JOIN pa_permission permission ON permission.`key` IN ('platform.settings.read', 'platform.settings.manage')
WHERE role.`key` = 'platform.bootstrap-owner'
ON DUPLICATE KEY UPDATE granted_at = VALUES(granted_at)
SQL);
    }

    /** @param list<mixed> $parameters */
    private function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
