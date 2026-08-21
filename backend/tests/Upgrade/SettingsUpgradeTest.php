<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Upgrade;

use PDO;
use PeanutAdmin\App\command\UpgradeWorkflow;
use PeanutAdmin\App\upgrade\BackupManifest;
use PeanutAdmin\App\upgrade\ReleaseManifest;
use PeanutAdmin\App\upgrade\RepositoryState;
use PeanutAdmin\App\upgrade\TargetMigrationInventory;
use PeanutAdmin\App\upgrade\UpgradePlan;
use PeanutAdmin\App\upgrade\UpgradePreflight;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SettingsUpgradeTest extends TestCase
{
    private const DATABASE = 'peanut_admin_p1_b03_settings_upgrade_test';
    private const ROLLBACK_DATABASE = 'peanut_admin_p1_b03_settings_rollback_test';
    private const OLD_LOCK = '0ab02a9b735ba9f4c23509cb366b9bf04039ebf8';
    private const OLD_LOCK_TREE = '12fdd00c1d506ca860b76dcc9e2dd796d56b723f';
    private const FIXTURE_EMAIL = 'p1-b03-old-lock@example.test';
    private const FIXTURE_PASSWORD = 'P1-B03-old-lock-fixture-2026!';
    private const FIXTURE_TENANT = 'p1-b03-old-lock';

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
            self::markTestSkipped('Run through the B03 integration gate.');
        }
        $this->requiredPort('MYSQL_PORT');
        $this->requiredPort('DB_PORT');

        $this->admin = $this->connect('MYSQL_PORT');
        $this->dropDatabase(self::DATABASE);
        $this->dropDatabase(self::ROLLBACK_DATABASE);
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE
            . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->database = $this->connect('DB_PORT', self::DATABASE);
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->dropDatabase(self::DATABASE);
            $this->dropDatabase(self::ROLLBACK_DATABASE);
        }
    }

    public function testQualificationEntryPointsRejectMissingDatabasePorts(): void
    {
        $root = dirname(__DIR__, 3);
        $entrypoints = [
            ['scripts/check'],
            ['scripts/test-integration'],
            ['scripts/test-security', '--php-only'],
            ['scripts/verify-internal-starter'],
        ];

        foreach (['MYSQL_PORT', 'DB_PORT'] as $missing) {
            foreach ($entrypoints as $entrypoint) {
                [$exitCode, $stdout, $stderr] = $this->process(
                    ['env', '-u', $missing, $root . '/' . $entrypoint[0], ...array_slice($entrypoint, 1)],
                    $root,
                    [],
                );

                self::assertSame(1, $exitCode, $entrypoint[0] . " accepted a missing {$missing}.");
                self::assertSame('', $stdout);
                self::assertStringContainsString("ERROR: {$missing} is required", $stderr);
            }
        }

        foreach (['MYSQL_PORT', 'DB_PORT'] as $missing) {
            foreach ([
                'frontend/tests/fixtures/full-stack-setup.php',
                'starter/backend/tests/settings.php',
            ] as $directRunner) {
                [$exitCode, $stdout, $stderr] = $this->process(
                    ['env', '-u', $missing, PHP_BINARY, $root . '/' . $directRunner],
                    $root,
                    [],
                );
                self::assertSame(1, $exitCode, "{$directRunner} accepted a missing {$missing}.");
                self::assertSame('', $stdout);
                self::assertStringContainsString("ERROR: {$missing} is required", $stderr);
            }

            [$exitCode, $stdout, $stderr] = $this->process(
                [
                    'env', '-u', $missing,
                    PHP_BINARY,
                    $root . '/vendor/bin/phpunit',
                    'backend/tests/Upgrade/SettingsUpgradeTest.php',
                    '--filter',
                    'testUpgradeInstallsAndSynchronizesSettingsIdempotently',
                ],
                $root,
                ['PEANUT_INTEGRATION' => '1'],
            );
            self::assertNotSame(0, $exitCode, "The upgrade runner accepted a missing {$missing}.");
            self::assertStringContainsString(
                "Missing required environment variable: {$missing}.",
                $stdout . $stderr,
            );

            foreach ([
                'packages/php/settings/tests/Integration/Application/SettingAdminServiceTest.php',
                'backend/tests/Integration/SettingsModuleIntegrationTest.php',
                'packages/php/kernel/tests/Integration/Host/ExternalOperationHostIntegrationTest.php',
                'backend/tests/Install/InstallWorkflowIntegrationTest.php',
                'backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php',
            ] as $focusedTest) {
                [$exitCode, $stdout, $stderr] = $this->process(
                    [
                        'env', '-u', $missing,
                        PHP_BINARY,
                        $root . '/vendor/bin/phpunit',
                        $focusedTest,
                        '--filter',
                        '/::test/',
                    ],
                    $root,
                    ['PEANUT_INTEGRATION' => '1'],
                );
                self::assertNotSame(0, $exitCode, "{$focusedTest} accepted a missing {$missing}.");
                self::assertStringContainsString(
                    "Missing required environment variable: {$missing}.",
                    $stdout . $stderr,
                );
            }
        }
    }

    public function testUpgradeInstallsAndSynchronizesSettingsIdempotently(): void
    {
        $workflow = new UpgradeWorkflow(dirname(__DIR__, 3), $this->database);

        $first = $workflow->installEmptyDatabase();
        self::assertContains('peanut.settings', $first['modules']);
        self::assertSame(16, $first['applied_module_migrations']);
        self::assertSame(4, $this->settingsTableCount($this->database));
        self::assertSame(self::SETTINGS_MIGRATIONS, $this->columnValues(
            $this->database,
            <<<'SQL'
SELECT migration_key FROM pa_module_migration
WHERE module_key = 'peanut.settings' AND status = 'applied'
ORDER BY id
SQL,
        ));
        self::assertSame(1, $this->scalar($this->database, <<<'SQL'
SELECT COUNT(DISTINCT batch_no) FROM pa_module_migration
WHERE module_key = 'peanut.settings' AND status = 'applied'
SQL));
        self::assertSame([
            'example.target:display-density:active:1',
            'example.target:fixture-secret:active:1',
        ], $this->columnValues($this->database, <<<'SQL'
SELECT CONCAT(module_key, ':', setting_key, ':', status, ':', revision)
FROM pa_setting_definition
ORDER BY module_key, setting_key
SQL));
        $definitions = $this->columnValues($this->database, <<<'SQL'
SELECT CONCAT(module_key, ':', setting_key, ':', definition_digest, ':', revision, ':', updated_at)
FROM pa_setting_definition
ORDER BY module_key, setting_key
SQL);

        $second = $workflow->assertCurrentReleaseNoop();

        self::assertSame(0, $second['applied_module_migrations']);
        self::assertSame($definitions, $this->columnValues($this->database, <<<'SQL'
SELECT CONCAT(module_key, ':', setting_key, ':', definition_digest, ':', revision, ':', updated_at)
FROM pa_setting_definition
ORDER BY module_key, setting_key
SQL));
        self::assertSame(0, $this->scalar($this->database, <<<'SQL'
SELECT COUNT(*) FROM pa_module_migration
WHERE status IN ('applying', 'rolled_back', 'failed')
SQL));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testOldLockRunsAgainstUpgradedDatabaseAndRollbackRestoresBackup(): void
    {
        $root = dirname(__DIR__, 3);
        $temporary = sys_get_temp_dir() . '/peanut-admin-p1-b03-old-lock-' . bin2hex(random_bytes(8));
        $oldRoot = $temporary . '/old-lock';
        $targetRoot = $temporary . '/target';
        $backup = $temporary . '/pre-upgrade-backup';
        $runner = $temporary . '/old-lock-runner.php';
        self::assertTrue(mkdir($temporary, 0700, true));
        self::assertNotFalse(file_put_contents($runner, $this->oldLockRunnerSource()));

        $worktreeAttached = false;
        try {
            $this->runCommand(['git', 'clone', '--quiet', '--no-hardlinks', $root, $targetRoot], $root);
            $this->runCommand(['git', 'worktree', 'add', '--detach', $oldRoot, self::OLD_LOCK], $root);
            $worktreeAttached = true;
            self::assertSame(self::OLD_LOCK, $this->runCommand(['git', 'rev-parse', 'HEAD'], $oldRoot));
            self::assertSame(self::OLD_LOCK_TREE, $this->runCommand(['git', 'rev-parse', 'HEAD^{tree}'], $oldRoot));
            self::assertTrue(symlink($root . '/vendor', $oldRoot . '/vendor'));

            $environment = $this->oldLockEnvironment($root, $oldRoot, self::DATABASE);
            $install = $this->json($this->runCommand([PHP_BINARY, $runner, 'install'], $root, $environment));
            self::assertSame(self::OLD_LOCK, $install['commit'] ?? null);
            self::assertSame(self::OLD_LOCK_TREE, $install['tree'] ?? null);
            self::assertSame('installed', $install['status'] ?? null);
            self::assertSame(3, $install['module_migrations'] ?? null);
            self::assertSame(0, $this->settingsTableCount($this->database));

            $this->runCommand(
                [$root . '/scripts/backup-mysql', '--output', $backup],
                $root,
                $this->databaseEnvironment(self::DATABASE),
            );
            self::assertFileExists($backup . '/manifest.json');
            self::assertFileExists($backup . '/dump.sql');

            $preUpgradeTableSignatures = $this->tableSignatures($this->database);
            $preUpgradeTables = array_map(
                static fn(string $value): string => strstr($value, ':', true) ?: $value,
                $preUpgradeTableSignatures,
            );
            $upgrade = (new UpgradeWorkflow($targetRoot, $this->database))
                ->run($this->upgradePlan($targetRoot, $oldRoot));
            self::assertSame(13, $upgrade['applied_module_migrations']);
            self::assertSame(4, $this->settingsTableCount($this->database));
            self::assertSame(self::SETTINGS_MIGRATIONS, $this->columnValues(
                $this->database,
                <<<'SQL'
SELECT migration_key FROM pa_module_migration
WHERE module_key = 'peanut.settings' AND status = 'applied'
ORDER BY id
SQL,
            ));
            self::assertSame(0, $this->scalar($this->database, <<<'SQL'
SELECT COUNT(*) FROM pa_module_migration WHERE status = 'rolled_back'
SQL));
            self::assertSame($preUpgradeTableSignatures, $this->tableSignatures(
                $this->database,
                [],
                $preUpgradeTables,
            ));

            $compatibility = $this->json($this->runCommand(
                [PHP_BINARY, $runner, 'compatibility'],
                $root,
                $environment,
            ));
            $this->assertCompatibilityResult($compatibility);

            $this->runCommand(
                [$root . '/scripts/restore-mysql', '--backup', $backup, '--target', self::ROLLBACK_DATABASE],
                $root,
                $this->databaseEnvironment(self::DATABASE),
            );
            $rollback = $this->connect('DB_PORT', self::ROLLBACK_DATABASE);
            self::assertSame(0, $this->settingsTableCount($rollback));
            self::assertSame(0, $this->scalar($rollback, <<<'SQL'
SELECT COUNT(*) FROM pa_module_migration WHERE module_key = 'peanut.settings'
SQL));
            self::assertSame(
                array_map(static fn(string $value): string => strstr($value, ':', true) ?: $value, $preUpgradeTableSignatures),
                array_map(
                    static fn(string $value): string => strstr($value, ':', true) ?: $value,
                    $this->tableSignatures($rollback),
                ),
            );
            self::assertSame(3, $this->scalar($rollback, <<<'SQL'
SELECT COUNT(*) FROM pa_module_migration WHERE status = 'applied'
SQL));
            self::assertSame(0, $this->scalar($rollback, <<<'SQL'
SELECT COUNT(*) FROM pa_module_migration WHERE status <> 'applied'
SQL));
            self::assertSame(4, $this->settingsTableCount($this->database));

            $rollbackCompatibility = $this->json($this->runCommand(
                [PHP_BINARY, $runner, 'compatibility'],
                $root,
                $this->oldLockEnvironment($root, $oldRoot, self::ROLLBACK_DATABASE),
            ));
            $this->assertCompatibilityResult($rollbackCompatibility);
        } finally {
            if ($worktreeAttached) {
                $this->runUnchecked(['git', 'worktree', 'remove', '--force', $oldRoot], $root);
            }
            $this->removeDirectory($temporary);
            $this->runUnchecked(['git', 'worktree', 'prune'], $root);
        }
    }

    /** @param array<string, mixed> $result */
    private function assertCompatibilityResult(array $result): void
    {
        self::assertSame(self::OLD_LOCK, $result['commit'] ?? null);
        self::assertSame(self::OLD_LOCK_TREE, $result['tree'] ?? null);
        self::assertSame('healthy', $result['health'] ?? null);
        self::assertSame(75, $result['p0_routes_total'] ?? null);
        self::assertSame(75, $result['p0_routes_dispatched'] ?? null);
        self::assertSame(true, $result['tenant_login'] ?? null);
        self::assertSame(true, $result['platform_login'] ?? null);
        self::assertSame(true, $result['external_module_host'] ?? null);
        self::assertSame(true, $result['external_client_isolation'] ?? null);
    }

    /** @return array<string, string> */
    private function oldLockEnvironment(string $root, string $oldRoot, string $database): array
    {
        return [
            ...$this->databaseEnvironment($database),
            'AUTH_IDENTIFIER_HMAC_KEY' => 'p1-b03-old-lock-compatibility-hmac-key',
            'CURRENT_VENDOR_AUTOLOAD' => $root . '/vendor/autoload.php',
            'OLD_LOCK_ROOT' => $oldRoot,
            'OLD_LOCK_COMMIT' => self::OLD_LOCK,
            'OLD_LOCK_TREE' => self::OLD_LOCK_TREE,
            'OLD_LOCK_FIXTURE_EMAIL' => self::FIXTURE_EMAIL,
            'OLD_LOCK_FIXTURE_PASSWORD' => self::FIXTURE_PASSWORD,
            'OLD_LOCK_FIXTURE_TENANT' => self::FIXTURE_TENANT,
        ];
    }

    private function upgradePlan(string $root, string $oldRoot): UpgradePlan
    {
        $source = (new TargetMigrationInventory())->scan($oldRoot);
        $target = (new TargetMigrationInventory())->scan($root);
        $targetCommit = trim($this->runCommand(['git', 'rev-parse', 'HEAD'], $root));
        $targetTree = trim($this->runCommand(['git', 'rev-parse', 'HEAD^{tree}'], $root));
        $release = ReleaseManifest::fromArray([
            'schema_version' => 1,
            'release_id' => 'settings-old-lock-integration',
            'source' => ['commit' => self::OLD_LOCK, 'tree' => self::OLD_LOCK_TREE],
            'target' => ['commit' => $targetCommit, 'tree' => $targetTree],
            'migrations' => ['source' => $source->entries, 'target' => $target->entries],
        ]);
        $backup = BackupManifest::fromArray([
            'schema_version' => 1,
            'backup_id' => 'settings-old-lock-backup',
            'environment' => 'test',
            'source' => $release->source,
            'artifact_sha256' => str_repeat('6', 64),
            'created_at' => '2026-07-24T00:00:00Z',
            'verified_at' => '2026-07-24T00:01:00Z',
            'restore_tested_at' => '2026-07-24T00:02:00Z',
        ]);

        return (new UpgradePreflight())->run(
            $release,
            $backup,
            new RepositoryState($targetCommit, $targetTree, true),
            $target,
            'test',
        );
    }

    /** @return array<string, string> */
    private function databaseEnvironment(string $database): array
    {
        return [
            'COMPOSE_PROJECT_NAME' => getenv('COMPOSE_PROJECT_NAME') ?: 'peanut-admin',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $this->requiredEnvironment('DB_PORT'),
            'DB_DATABASE' => $database,
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev',
            'MYSQL_PORT' => $this->requiredEnvironment('MYSQL_PORT'),
            'MYSQL_ROOT_PASSWORD' => getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev',
            'CACHE_HOST' => '127.0.0.1',
            'CACHE_PORT' => (string) (getenv('CACHE_PORT') ?: 6379),
        ];
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function runCommand(array $command, string $workingDirectory, array $environment = []): string
    {
        [$exitCode, $stdout, $stderr] = $this->process($command, $workingDirectory, $environment);
        if ($exitCode !== 0) {
            self::fail(sprintf(
                "Command failed (%d): %s\nSTDOUT:\n%s\nSTDERR:\n%s",
                $exitCode,
                implode(' ', array_map('escapeshellarg', $command)),
                $stdout,
                $stderr,
            ));
        }

        return trim($stdout);
    }

    /** @param list<string> $command */
    private function runUnchecked(array $command, string $workingDirectory): void
    {
        $this->process($command, $workingDirectory, []);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @return array{int, string, string}
     */
    private function process(array $command, string $workingDirectory, array $environment): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $baseEnvironment = getenv();
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory,
            [...$baseEnvironment, ...$environment],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('The compatibility subprocess could not be started.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, (string) $stdout, (string) $stderr];
    }

    /** @return array<string, mixed> */
    private function json(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
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
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    private function requiredPort(string $name): int
    {
        $value = $this->requiredEnvironment($name);
        if (preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new RuntimeException("Invalid port in environment variable: {$name}.");
        }
        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException("Invalid port in environment variable: {$name}.");
        }

        return $port;
    }

    private function requiredEnvironment(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Missing required environment variable: {$name}.");
        }

        return $value;
    }

    private function dropDatabase(string $database): void
    {
        $this->admin->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    }

    private function settingsTableCount(PDO $database): int
    {
        return $this->scalar($database, <<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'pa_setting_definition',
    'pa_setting_deployment_value',
    'pa_setting_tenant_value',
    'pa_setting_target_value'
  )
SQL);
    }

    private function scalar(PDO $database, string $sql): int
    {
        $statement = $database->query($sql);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function columnValues(PDO $database, string $sql): array
    {
        $statement = $database->query($sql);
        self::assertNotFalse($statement);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @param list<string> $excludedTables
     * @param list<string>|null $includedTables
     * @return list<string>
     */
    private function tableSignatures(
        PDO $database,
        array $excludedTables = [],
        ?array $includedTables = null,
    ): array {
        $statement = $database->query(<<<'SQL'
SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
ORDER BY table_name
SQL);
        self::assertNotFalse($statement);
        $signatures = [];
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            $table = (string) $row[0];
            if (in_array($table, $excludedTables, true)
                || ($includedTables !== null && !in_array($table, $includedTables, true))) {
                continue;
            }
            $columns = $database->query('SHOW CREATE TABLE `' . $table . '`');
            self::assertNotFalse($columns);
            $create = $columns->fetch(PDO::FETCH_NUM);
            self::assertIsArray($create);
            $normalized = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) ($create[1] ?? ''));
            self::assertIsString($normalized);
            $signatures[] = $table . ':' . hash('sha256', $normalized);
        }

        return $signatures;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($directory);
    }

    private function oldLockRunnerSource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\middleware\PlatformAuthRuntimeFactory;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\InternalStarter\Auth\TenantAuthRuntimeFactory as ExternalTenantAuthRuntimeFactory;
use PeanutAdmin\InternalStarter\Module\ModuleRegistryFactory as ExternalModuleRegistryFactory;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\PlatformAuthentication;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use think\App;
use think\Request;

require requiredEnvironment('CURRENT_VENDOR_AUTOLOAD');

$oldRoot = requiredEnvironment('OLD_LOCK_ROOT');
$prefixes = [
    'PeanutAdmin\\InternalStarter\\' => $oldRoot . '/starter/backend/src/',
    'ExampleHost\\App\\Modules\\' => $oldRoot . '/starter/backend/src/Modules/',
    'PeanutAdmin\\DataPermission\\' => $oldRoot . '/packages/php/data-permission/src/',
    'PeanutAdmin\\Testing\\' => $oldRoot . '/packages/php/testing/src/',
    'PeanutAdmin\\Kernel\\' => $oldRoot . '/packages/php/kernel/src/',
    'PeanutAdmin\\App\\' => $oldRoot . '/backend/app/',
];
spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
}, true, true);

$installed = InstalledVersions::getRawData();
$installed['versions']['peanut-admin/kernel']['install_path'] = $oldRoot . '/packages/php/kernel';
$installed['versions']['peanut-admin/data-permission']['install_path'] = $oldRoot . '/packages/php/data-permission';
$installed['versions']['peanut-admin/testing']['install_path'] = $oldRoot . '/packages/php/testing';
InstalledVersions::reload($installed);

$mode = $argv[1] ?? '';
$pdo = connection();
if ($mode === 'install') {
    $profile = InstallProductProfile::load(
        $oldRoot . '/profiles/reference-admin.json',
        $oldRoot . '/schemas/product-profile.schema.json',
    );
    $result = (new InstallWorkflow($oldRoot, $pdo))->run(
        $profile,
        requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
        requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
        'P1 B03 Old Lock Owner',
        [
            'code' => requiredEnvironment('OLD_LOCK_FIXTURE_TENANT'),
            'name' => 'P1 B03 Old Lock Tenant',
            'owner_email' => requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
            'owner_name' => 'P1 B03 Old Lock Owner',
        ],
    );
    emit([
        'commit' => trim(runGit($oldRoot, ['rev-parse', 'HEAD'])),
        'tree' => trim(runGit($oldRoot, ['rev-parse', 'HEAD^{tree}'])),
        'status' => $result['status'] ?? null,
        'module_migrations' => scalar($pdo, "SELECT COUNT(*) FROM pa_module_migration WHERE status = 'applied'"),
    ]);
}
if ($mode !== 'compatibility') {
    throw new RuntimeException('Unknown old-lock runner mode.');
}

$tenant = TenantAuthRuntimeFactory::create()->login(
    requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
    requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
    requiredEnvironment('OLD_LOCK_FIXTURE_TENANT'),
    '127.0.0.1',
    'P1 B03 old-lock compatibility',
    'p1-b03-old-lock-tenant-login-' . bin2hex(random_bytes(4)),
);
if (!$tenant instanceof TenantAuthentication) {
    throw new RuntimeException('The old-lock Tenant login did not authenticate.');
}
$platform = PlatformAuthRuntimeFactory::create()->login(
    requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
    requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
    '127.0.0.1',
    'P1 B03 old-lock compatibility',
    'p1-b03-old-lock-platform-login-' . bin2hex(random_bytes(4)),
);
if (!$platform instanceof PlatformAuthentication) {
    throw new RuntimeException('The old-lock platform login did not authenticate.');
}

$health = request($oldRoot, 'GET', '/api/v1/health', null, []);
if ($health['status'] !== 200 || ($health['data']['status'] ?? null) !== 'healthy') {
    throw new RuntimeException('The old-lock health path is not healthy on the candidate database.');
}

$coverage = json_decode(
    (string) file_get_contents($oldRoot . '/docs/status/runtime-operation-coverage.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$p0Operations = array_values(array_filter(
    $coverage['operations'] ?? [],
    static fn(mixed $operation): bool => is_array($operation) && ($operation['scope'] ?? null) === 'p0',
));
if (count($p0Operations) !== 75) {
    throw new RuntimeException('The old-lock Runtime coverage ledger does not contain exactly 75 P0 routes.');
}

$dispatched = 0;
foreach ($p0Operations as $index => $operation) {
    [$method, $path] = explode(' ', (string) $operation['route'], 2);
    $path = routePath($path);
    $operationId = (string) $operation['operation_id'];
    $headers = [
        'authorization' => 'Bearer ' . (
            str_starts_with($path, '/api/platform/')
                ? $platform->tokens->access->expose()
                : $tenant->tokens->access->expose()
        ),
        'x-request-id' => 'req_old_lock_route_' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
    ];
    $body = [];
    if ($operationId === 'tenantLogin') {
        $body = [
            'email' => requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
            'password' => requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
            'tenant_code' => requiredEnvironment('OLD_LOCK_FIXTURE_TENANT'),
        ];
        unset($headers['authorization']);
    } elseif ($operationId === 'platformLogin') {
        $body = [
            'email' => requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
            'password' => requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
        ];
        unset($headers['authorization']);
    } elseif (in_array($operationId, [
        'logoutTenantSession',
        'logoutAllTenantSessions',
        'logoutPlatformSession',
        'refreshTenantSession',
        'refreshPlatformSession',
        'selectTenant',
    ], true)) {
        unset($headers['authorization']);
    }
    $response = request($oldRoot, $method, $path, $body, $headers);
    $problemCode = is_array($response['data']) ? ($response['data']['code'] ?? null) : null;
    if ($response['status'] >= 500 || $problemCode === 'ROUTE_NOT_FOUND') {
        throw new RuntimeException(sprintf(
            'Old-lock P0 route failed on upgraded database: %s %s returned %d %s.',
            $method,
            $path,
            $response['status'],
            is_string($problemCode) ? $problemCode : '',
        ));
    }
    ++$dispatched;
}

$externalRegistry = (new ExternalModuleRegistryFactory($oldRoot . '/starter'))->compile();
if ($externalRegistry->moduleKeys() !== ['example.greeting']) {
    throw new RuntimeException('The old-lock external Module host did not compile its application namespace.');
}
$externalFactory = new ExternalTenantAuthRuntimeFactory(
    $pdo,
    new PasswordHasher(),
    $oldRoot . '/starter',
    'p1-b03-external-host-hmac-key-at-least-32-bytes',
);
$operationsEndpoint = new TenantAuthEndpoint($externalFactory->create('operations-web'));
$reporting = new TenantAuthEndpoint($externalFactory->create('reporting-web'));
$externalLogin = $operationsEndpoint->login(
    requiredEnvironment('OLD_LOCK_FIXTURE_EMAIL'),
    requiredEnvironment('OLD_LOCK_FIXTURE_PASSWORD'),
    requiredEnvironment('OLD_LOCK_FIXTURE_TENANT'),
    '127.0.0.1',
    'P1 B03 external host',
    'p1-b03-external-host-login-' . bin2hex(random_bytes(4)),
);
$externalAccess = $externalLogin->body['data']['access_token'] ?? null;
if (!is_string($externalAccess) || $externalAccess === '') {
    throw new RuntimeException('The old-lock external Tenant Client did not authenticate.');
}
$externalIsolation = false;
try {
    $reporting->context($externalAccess, 'p1-b03-external-host-cross-client');
} catch (AuthException $exception) {
    $externalIsolation = $exception->errorCode === 'AUTH_TOKEN_INVALID';
}
if (!$externalIsolation) {
    throw new RuntimeException('The old-lock external Tenant Client boundary was not enforced.');
}

emit([
    'commit' => trim(runGit($oldRoot, ['rev-parse', 'HEAD'])),
    'tree' => trim(runGit($oldRoot, ['rev-parse', 'HEAD^{tree}'])),
    'health' => $health['data']['status'],
    'p0_routes_total' => count($p0Operations),
    'p0_routes_dispatched' => $dispatched,
    'tenant_login' => true,
    'platform_login' => true,
    'external_module_host' => true,
    'external_client_isolation' => $externalIsolation,
]);

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Missing required environment variable: {$name}.");
    }

    return $value;
}

function connection(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            requiredEnvironment('DB_HOST'),
            (int) requiredEnvironment('DB_PORT'),
            requiredEnvironment('DB_DATABASE'),
        ),
        requiredEnvironment('DB_USERNAME'),
        requiredEnvironment('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
}

/** @param list<string> $arguments */
function runGit(string $root, array $arguments): string
{
    $command = ['git', '-C', $root, ...$arguments];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Git could not be started.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Git failed: ' . $stderr);
    }

    return (string) $stdout;
}

/** @return array{status: int, data: mixed} */
function request(string $root, string $method, string $url, ?array $body, array $headers): array
{
    $outputLevel = ob_get_level();
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
            'user-agent' => 'P1 B03 old-lock compatibility',
            ...$headers,
        ]);
    if ($body !== null) {
        $request->withPost($body)->withInput(json_encode($body, JSON_THROW_ON_ERROR));
    }
    $app = new App($root . '/backend');
    $http = $app->http;
    try {
        $response = $http->run($request);
        $http->end($response);

        return ['status' => $response->getCode(), 'data' => $response->getData()];
    } finally {
        while (ob_get_level() > $outputLevel) {
            ob_end_clean();
        }
        restore_error_handler();
        restore_exception_handler();
    }
}

function routePath(string $path): string
{
    $values = [
        'department_id' => '999999999',
        'member_id' => '999999999',
        'module_key' => 'missing.module',
        'operation' => 'list',
        'operator_id' => '999999999',
        'resource_key' => 'example.work-item',
        'role_id' => '999999999',
        'tenant_id' => '999999999',
        'work_item_id' => '999999999',
    ];

    return preg_replace_callback(
        '/\{([^}]+)\}/',
        static fn(array $match): string => $values[$match[1]] ?? '999999999',
        $path,
    ) ?? $path;
}

function scalar(PDO $pdo, string $sql): int
{
    $statement = $pdo->query($sql);
    if ($statement === false) {
        throw new RuntimeException('Scalar query failed.');
    }

    return (int) $statement->fetchColumn();
}

/** @param array<string, mixed> $result */
function emit(array $result): never
{
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
    exit(0);
}
PHP;
    }
}
