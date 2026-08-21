<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Install;

use PDO;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallWorkflowIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_ops_install_test';

    private const SETTINGS_MIGRATIONS = [
        'peanut.settings:20260719030101_create_setting_definitions',
        'peanut.settings:20260719030102_create_setting_deployment_values',
        'peanut.settings:20260719030103_create_setting_tenant_values',
        'peanut.settings:20260719030104_create_setting_target_values',
    ];

    private PDO $admin;
    private PDO $database;

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through the D01 integration gate.');
        }
        $this->requiredPort('MYSQL_PORT');
        $this->requiredPort('DB_PORT');

        $this->admin = $this->connect('MYSQL_PORT');
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE
            . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->database = $this->connect('DB_PORT', self::DATABASE);
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
    }

    public function testFreshInstallBootstrapsPlatformTenantProfileAndDefaultDepartment(): void
    {
        $root = dirname(__DIR__, 3);
        $profile = InstallProductProfile::load(
            $root . '/profiles/reference-admin.json',
            $root . '/schemas/product-profile.schema.json',
        );
        $workflow = new InstallWorkflow($root, $this->database);

        $result = $workflow->run(
            $profile,
            'owner@example.test',
            'correct horse battery staple',
            'Platform Owner',
            [
                'code' => 'first-tenant',
                'name' => 'First Tenant',
                'owner_email' => 'owner@example.test',
                'owner_name' => 'Tenant Owner',
            ],
        );

        self::assertSame('installed', $result['status']);
        self::assertSame(1, $this->countRows('pa_account'));
        self::assertSame(1, $this->countRows('pa_platform_operator'));
        self::assertSame(1, $this->countRows('pa_tenant'));
        self::assertSame(10, $this->countRows('pa_tenant_module'));
        self::assertSame(1, $this->countRows('pa_department'));
        self::assertSame(1, $this->countRows('pa_role'));
        self::assertArrayNotHasKey('password', $result);
        self::assertSame([
            'example.target',
            'example.reference',
            'example.work-item',
            'peanut.file-media',
            'peanut.task-job',
            'peanut.import-export',
            'peanut.integration-security',
            'peanut.notification-sms',
            'peanut.reference-codes',
            'peanut.settings',
        ], $result['upgrade']['modules']);
        self::assertSame(16, $result['upgrade']['applied_module_migrations']);
        self::assertSame(4, $this->countExistingTables([
            'pa_setting_definition',
            'pa_setting_deployment_value',
            'pa_setting_tenant_value',
            'pa_setting_target_value',
        ]));
        self::assertSame(self::SETTINGS_MIGRATIONS, $this->columnValues(<<<'SQL'
SELECT migration_key FROM pa_module_migration
WHERE module_key = 'peanut.settings' AND status = 'applied'
ORDER BY id
SQL));
        self::assertSame([
            'example.target:display-density:active:1',
            'example.target:fixture-secret:active:1',
        ], $this->columnValues(<<<'SQL'
SELECT CONCAT(module_key, ':', setting_key, ':', status, ':', revision)
FROM pa_setting_definition
ORDER BY module_key, setting_key
SQL));
        $definitionDigests = $this->columnValues(<<<'SQL'
SELECT definition_digest FROM pa_setting_definition ORDER BY module_key, setting_key
SQL);

        $repeat = $workflow->run(
            $profile,
            'owner@example.test',
            'correct horse battery staple',
            'Platform Owner',
            null,
            true,
        );
        self::assertSame('already-installed', $repeat['status']);
        self::assertSame(0, $repeat['upgrade']['applied_module_migrations']);
        self::assertSame($definitionDigests, $this->columnValues(<<<'SQL'
SELECT definition_digest FROM pa_setting_definition ORDER BY module_key, setting_key
SQL));
        self::assertSame(0, $this->countRowsWhere("pa_module_migration", "status <> 'applied'"));

        $this->expectException(RuntimeException::class);
        $workflow->run(
            $profile,
            'owner@example.test',
            'correct horse battery staple',
            'Platform Owner',
        );
    }

    private function connect(string $portEnvironment, ?string $database = null): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=127.0.0.1;port=%d%s;charset=utf8mb4',
                $this->requiredPort($portEnvironment),
                $database === null ? '' : ';dbname=' . $database,
            ),
            'root',
            getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
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

    private function countRows(string $table): int
    {
        $statement = $this->database->query("SELECT COUNT(*) FROM `{$table}`");
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    /** @param list<string> $tables */
    private function countExistingTables(array $tables): int
    {
        $quoted = implode(', ', array_fill(0, count($tables), '?'));
        $statement = $this->database->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
            . " AND table_name IN ({$quoted})",
        );
        $statement->execute($tables);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function columnValues(string $sql): array
    {
        $statement = $this->database->query($sql);
        self::assertNotFalse($statement);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function countRowsWhere(string $table, string $predicate): int
    {
        $statement = $this->database->query("SELECT COUNT(*) FROM `{$table}` WHERE {$predicate}");
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }
}
