<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Upgrade;

use PDO;
use PeanutAdmin\App\command\UpgradeWorkflow;
use PeanutAdmin\App\upgrade\BackupManifest;
use PeanutAdmin\App\upgrade\MigrationInventory;
use PeanutAdmin\App\upgrade\ReleaseManifest;
use PeanutAdmin\App\upgrade\RepositoryInspector;
use PeanutAdmin\App\upgrade\RepositoryState;
use PeanutAdmin\App\upgrade\TargetMigrationInventory;
use PeanutAdmin\App\upgrade\UpgradePlan;
use PeanutAdmin\App\upgrade\UpgradePreflight;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use PeanutAdmin\Kernel\Module\ModuleException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[PreserveGlobalState(false)]
#[RunClassInSeparateProcess]
final class UpgradeWorkflowIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_ops_upgrade_test';
    private const OLD_RELEASE = '0ab02a9b735ba9f4c23509cb366b9bf04039ebf8';

    private PDO $admin;
    private PDO $database;
    private static string $repositoryRoot;

    public static function setUpBeforeClass(): void
    {
        self::$repositoryRoot = sys_get_temp_dir() . '/peanut-upgrade-git-' . bin2hex(random_bytes(8));
        self::runCommand([
            'git', 'clone', '--quiet', '--no-hardlinks', dirname(__DIR__, 3), self::$repositoryRoot,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$repositoryRoot)) {
            self::removeDirectory(self::$repositoryRoot);
        }
    }

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

    public function testUpgradeRunsKernelDataAndModulesInDependencyOrderAndIsIdempotent(): void
    {
        $root = self::$repositoryRoot;
        $workflow = new UpgradeWorkflow($root, $this->database);

        $first = $workflow->installEmptyDatabase();
        $second = $workflow->assertCurrentReleaseNoop();

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
        ], $first['modules']);
        self::assertSame(16, $first['applied_module_migrations']);
        self::assertSame(0, $second['applied_module_migrations']);
        self::assertSame(10, $this->scalar("SELECT COUNT(*) FROM pa_module_installation WHERE status = 'active'"));
        self::assertSame(16, $this->scalar("SELECT COUNT(*) FROM pa_module_migration WHERE status = 'applied'"));
        self::assertSame(88, $this->scalar("SELECT COUNT(*) FROM pa_permission WHERE status = 'active'"));
        self::assertSame(1, $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM pa_permission
WHERE `key` = 'core.member.effective-access.read'
  AND module_key = 'core' AND type = 'api' AND risk_level = 'sensitive' AND status = 'active'
SQL));
        self::assertSame(10, $this->scalar("SELECT COUNT(*) FROM pa_protected_resource WHERE status = 'active'"));
        self::assertSame(2, $this->scalar("SELECT COUNT(*) FROM pa_target_type WHERE status = 'active'"));
        self::assertSame(35, $this->scalar("SELECT COUNT(*) FROM pa_resource_operation WHERE status = 'active'"));
        self::assertSame(17, $this->scalar("SELECT COUNT(*) FROM pa_resource_operation_target_type WHERE status = 'active'"));
        self::assertSame(6, $this->scalar("SELECT COUNT(*) FROM pa_data_condition_definition WHERE status = 'active'"));
        self::assertSame(40, $this->scalar("SELECT COUNT(*) FROM pa_resource_operation_condition WHERE status = 'active'"));
        self::assertSame(27, $this->scalar("SELECT COUNT(*) FROM pa_menu_definition WHERE status = 'active'"));
        $menus = new PdoMenuCatalogRepository($this->database);
        self::assertCount(19, $menus->activeDefinitions('tenant'));
        self::assertCount(8, $menus->activeDefinitions('platform'));
        self::assertSame([
            'peanut.settings:20260719030101_create_setting_definitions',
            'peanut.settings:20260719030102_create_setting_deployment_values',
            'peanut.settings:20260719030103_create_setting_tenant_values',
            'peanut.settings:20260719030104_create_setting_target_values',
        ], $this->columnValues(<<<'SQL'
SELECT migration_key FROM pa_module_migration
WHERE module_key = 'peanut.settings' AND status = 'applied'
ORDER BY id
SQL));
        self::assertSame(2, $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM pa_setting_definition
WHERE module_key = 'example.target' AND status = 'active' AND revision = 1
SQL));
        self::assertSame(1, $this->scalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '"
            . self::DATABASE . "' AND table_name = 'pa_data_permission_policy'",
        ));
    }

    public function testEvidenceBoundUpgradeRejectsTheWrongSourceDatabaseBeforeMutation(): void
    {
        $root = self::$repositoryRoot;
        $parentLine = self::runCommand(['git', '-C', $root, 'rev-list', '--parents', '-n', '1', 'HEAD']);
        $sourceRevision = count(preg_split('/\s+/', $parentLine) ?: []) > 2 ? 'HEAD^2' : 'HEAD^';
        $sourceCommit = self::runCommand(['git', '-C', $root, 'rev-parse', $sourceRevision]);
        $source = (new RepositoryInspector())->inventoryAtCommit($root, $sourceCommit);

        try {
            (new UpgradeWorkflow($root, $this->database))->run(
                $this->plan($root, $source, null, $sourceRevision),
            );
        } catch (ModuleException $exception) {
            self::assertSame('UPGRADE_SOURCE_DATABASE_MISMATCH', $exception->errorCode);
            self::assertSame(0, $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'peanut_admin_ops_upgrade_test' AND table_name = 'pa_account'
SQL));

            return;
        }

        self::fail('An evidence-bound upgrade must reject a database that is not at the declared source.');
    }

    public function testAppliedMigrationChecksumDriftStopsBeforeFurtherChanges(): void
    {
        $workflow = new UpgradeWorkflow(self::$repositoryRoot, $this->database);
        $workflow->installEmptyDatabase();
        $this->database->exec(
            "UPDATE pa_module_migration SET checksum = REPEAT('0', 64)"
            . " WHERE module_key = 'example.target'",
        );

        try {
            $workflow->assertCurrentReleaseNoop();
        } catch (ModuleException $exception) {
            self::assertSame('MODULE_MIGRATION_CHECKSUM_MISMATCH', $exception->errorCode);
            self::assertSame(16, $this->scalar(
                "SELECT COUNT(*) FROM pa_module_migration WHERE status = 'applied'",
            ));

            return;
        }

        self::fail('Checksum drift must stop the upgrade.');
    }

    public function testEvidenceBoundOldReleaseUpgradesToCurrentAndRepeatsAsNoop(): void
    {
        $root = self::$repositoryRoot;
        $oldRoot = sys_get_temp_dir() . '/peanut-upgrade-old-' . bin2hex(random_bytes(8));
        try {
            self::runCommand(['git', 'clone', '--quiet', '--no-hardlinks', $root, $oldRoot]);
            self::runCommand(['git', '-C', $oldRoot, 'checkout', '--quiet', '--detach', self::OLD_RELEASE]);
            $old = $this->installOldRelease($oldRoot);
            $source = (new TargetMigrationInventory())->scan($oldRoot);

            $result = (new UpgradeWorkflow($root, $this->database))->run(
                $this->plan($root, $source, $oldRoot),
            );
            $repeat = (new UpgradeWorkflow($root, $this->database))->assertCurrentReleaseNoop();

            self::assertSame(3, $old['applied_module_migrations']);
            self::assertSame(13, $result['applied_module_migrations']);
            self::assertSame(0, $repeat['applied_module_migrations']);
            self::assertSame(1, $this->scalar(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '"
                . self::DATABASE . "' AND table_name = 'pa_file_object'",
            ));
        } finally {
            self::removeDirectory($oldRoot);
        }
    }

    public function testConcurrentUpgradeLockFailsBeforeChangingSchema(): void
    {
        $lockKey = 'pa:upgrade:' . substr(hash('sha256', self::DATABASE), 0, 48);
        $lock = $this->database->prepare('SELECT GET_LOCK(:lock_key, 0)');
        $lock->execute(['lock_key' => $lockKey]);
        self::assertSame(1, (int) $lock->fetchColumn());

        try {
            (new UpgradeWorkflow(
                self::$repositoryRoot,
                $this->connect('DB_PORT', self::DATABASE),
            ))->installEmptyDatabase();
        } catch (ModuleException $exception) {
            self::assertSame('MODULE_UPGRADE_LOCKED', $exception->errorCode);
            self::assertSame(0, $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'peanut_admin_ops_upgrade_test' AND table_name = 'pa_account'
SQL));

            return;
        } finally {
            $release = $this->database->prepare('SELECT RELEASE_LOCK(:lock_key)');
            $release->execute(['lock_key' => $lockKey]);
        }

        self::fail('A concurrent upgrade lock must fail closed.');
    }

    public function testCurrentReleaseNoopRejectsDefinitionDigestDrift(): void
    {
        $workflow = new UpgradeWorkflow(self::$repositoryRoot, $this->database);
        $workflow->installEmptyDatabase();
        $this->database->exec("UPDATE pa_setting_definition SET definition_digest = REPEAT('0', 64) LIMIT 1");

        try {
            $workflow->assertCurrentReleaseNoop();
        } catch (ModuleException $exception) {
            self::assertSame('UPGRADE_EVIDENCE_REQUIRED', $exception->errorCode);

            return;
        }

        self::fail('Definition drift must not be accepted as a current-release no-op.');
    }

    public function testCurrentReleaseNoopRejectsAnExtraModuleInstallation(): void
    {
        $workflow = new UpgradeWorkflow(self::$repositoryRoot, $this->database);
        $workflow->installEmptyDatabase();
        $this->database->exec(<<<'SQL'
INSERT INTO pa_module_installation (
  module_key, installed_version, manifest_schema_version, manifest_digest,
  status, revision, created_at, updated_at
) VALUES ('unexpected.module', '1.0.0', 1, REPEAT('a', 64), 'active', 1, NOW(3), NOW(3))
SQL);

        try {
            $workflow->assertCurrentReleaseNoop();
        } catch (ModuleException $exception) {
            self::assertSame('UPGRADE_EVIDENCE_REQUIRED', $exception->errorCode);

            return;
        }

        self::fail('An extra Module installation must not be accepted as current.');
    }

    public function testCurrentReleaseNoopAllowsRetiredHistoryButRequiresCurrentDefinitionsActive(): void
    {
        $workflow = new UpgradeWorkflow(self::$repositoryRoot, $this->database);
        $workflow->installEmptyDatabase();
        $this->database->exec(<<<'SQL'
INSERT INTO pa_reference_code_set (
  module_key, set_key, name, description, definition_digest,
  lifecycle, revision, created_at, updated_at
) VALUES (
  'retired.module', 'legacy', 'Legacy', 'Retired history', REPEAT('a', 64),
  'retired', 1, NOW(3), NOW(3)
)
SQL);

        self::assertSame(0, $workflow->assertCurrentReleaseNoop()['applied_module_migrations']);

        $this->database->exec("UPDATE pa_setting_definition SET status = 'retired' LIMIT 1");
        try {
            $workflow->assertCurrentReleaseNoop();
        } catch (ModuleException $exception) {
            self::assertSame('UPGRADE_EVIDENCE_REQUIRED', $exception->errorCode);

            return;
        }

        self::fail('A definition required by the current registry must remain active.');
    }

    private function plan(
        string $root,
        MigrationInventory $source,
        ?string $sourceRoot = null,
        ?string $sourceRevision = null,
    ): UpgradePlan {
        $target = (new TargetMigrationInventory())->scan($root);
        $sourceRepository = $sourceRoot ?? $root;
        $sourceRevision ??= $sourceRoot === null ? 'HEAD^' : 'HEAD';
        $sourceCommit = self::runCommand(['git', '-C', $sourceRepository, 'rev-parse', $sourceRevision]);
        $sourceTree = self::runCommand([
            'git', '-C', $sourceRepository, 'rev-parse', $sourceCommit . '^{tree}',
        ]);
        $targetCommit = self::runCommand(['git', '-C', $root, 'rev-parse', 'HEAD']);
        $targetTree = self::runCommand(['git', '-C', $root, 'rev-parse', 'HEAD^{tree}']);
        $release = ReleaseManifest::fromArray([
            'schema_version' => 1,
            'release_id' => 'integration-upgrade',
            'source' => ['commit' => $sourceCommit, 'tree' => $sourceTree],
            'target' => ['commit' => $targetCommit, 'tree' => $targetTree],
            'migrations' => ['source' => $source->entries, 'target' => $target->entries],
        ]);
        $backup = BackupManifest::fromArray([
            'schema_version' => 1,
            'backup_id' => 'integration-backup',
            'environment' => 'test',
            'source' => $release->source,
            'artifact_sha256' => str_repeat('5', 64),
            'created_at' => '2026-07-24T00:00:00Z',
            'verified_at' => '2026-07-24T00:01:00Z',
            'restore_tested_at' => '2026-07-24T00:02:00Z',
        ]);

        return (new UpgradePreflight())->run(
            $release,
            $backup,
            new RepositoryState($release->target['commit'], $release->target['tree'], true),
            $target,
            'test',
        );
    }

    /** @return array{modules: list<string>, applied_module_migrations: int} */
    private function installOldRelease(string $root): array
    {
        $code = <<<'PHP'
require $argv[1];
$oldRoot = $argv[2];
$prefixes = [
    'PeanutAdmin\\DataPermission\\' => $oldRoot . '/packages/php/data-permission/src/',
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
$installed = Composer\InstalledVersions::getRawData();
$installed['versions']['peanut-admin/kernel']['install_path'] = $oldRoot . '/packages/php/kernel';
$installed['versions']['peanut-admin/data-permission']['install_path'] = $oldRoot . '/packages/php/data-permission';
Composer\InstalledVersions::reload($installed);
$pdo = new PDO(
    sprintf('mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4', getenv('DB_PORT'), getenv('DB_DATABASE')),
    'root',
    getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$result = (new PeanutAdmin\App\command\UpgradeWorkflow($oldRoot, $pdo))->run();
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
PHP;
        $command = [PHP_BINARY, '-r', $code, dirname(__DIR__, 3) . '/vendor/autoload.php', $root];
        $environment = [
            'DB_PORT' => (string) getenv('DB_PORT'),
            'DB_DATABASE' => self::DATABASE,
            'MYSQL_ROOT_PASSWORD' => (string) (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev'),
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        if (!is_resource($process)) {
            self::fail('Old release installer could not start.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        $result = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        /** @var array{modules: list<string>, applied_module_migrations: int} $result */
        return $result;
    }

    /** @param list<string> $command */
    private static function runCommand(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            self::fail('Git fixture command could not start.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, is_string($stderr) ? $stderr : 'Git fixture command failed.');

        return trim((string) $stdout);
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($path);
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

    private function scalar(string $sql): int
    {
        $statement = $this->database->query($sql);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function columnValues(string $sql): array
    {
        $statement = $this->database->query($sql);
        self::assertNotFalse($statement);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
