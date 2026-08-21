<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\App\module\ModuleRegistryFactory;
use PeanutAdmin\App\referencecode\ReferenceCodeRuntimeFactory;
use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\App\upgrade\MigrationInventory;
use PeanutAdmin\App\upgrade\RepositoryUpgradeTargetVerifier;
use PeanutAdmin\App\upgrade\TargetMigrationInventory;
use PeanutAdmin\App\upgrade\UpgradePlan;
use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use PeanutAdmin\Kernel\Authorization\ModuleAuthorizationCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Menu\MenuCatalogSynchronizer;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use PeanutAdmin\Kernel\Migration\MigrationRecord;
use PeanutAdmin\Kernel\Migration\ModuleMigrationLedger;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Package as KernelPackage;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Phinx\Migration\MigrationInterface;
use RuntimeException;
use think\console\Input;
use think\migration\NullOutput;
use Throwable;

final readonly class UpgradeWorkflow
{
    public function __construct(
        private string $root,
        private PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function fromEnvironment(string $root): self
    {
        return new self($root, self::connectFromEnvironment());
    }

    /**
     * Compatibility entry point for the post-install idempotency probe.
     * It never applies migrations or synchronizes catalogs. A real source to
     * target upgrade must use the explicit release and backup manifests.
     *
     * @return array{modules: list<string>, applied_module_migrations: int}
     */
    public function assertCurrentReleaseNoop(): array
    {
        if (!$this->acquireLock()) {
            throw new ModuleException('MODULE_UPGRADE_LOCKED', 'Another upgrade is already running.');
        }
        try {
            $registry = $this->registry();
            (new TargetMigrationInventory())->scan($this->root);
            if (!$this->tableExists('pa_module_migration')) {
                throw new ModuleException(
                    'UPGRADE_EVIDENCE_REQUIRED',
                    'Explicit release and backup manifests are required.',
                );
            }
            $this->assertPackageMigrationCurrent(
                $this->kernelMigrationsPath(),
                'pa_kernel_migration',
            );
            $this->assertPackageMigrationCurrent(
                $this->dataPermissionMigrationsPath(),
                'pa_data_permission_migration',
            );
            foreach ($registry->modules as $module) {
                $plan = $this->modulePlan($module);
                $this->assertModulePlan($plan);
                foreach ($plan['migrations'] as $migration) {
                    if ($this->migrationNeedsApply($plan['module_key'], $migration['key'])) {
                        throw new ModuleException(
                            'UPGRADE_EVIDENCE_REQUIRED',
                            'Explicit release and backup manifests are required.',
                        );
                    }
                }
                $installation = $this->installation($plan['module_key']);
                if ($installation === null
                    || $installation['installed_version'] !== $plan['module_version']
                    || $installation['manifest_digest'] !== $module->digest
                    || $installation['status'] !== 'active') {
                    throw new ModuleException(
                        'UPGRADE_EVIDENCE_REQUIRED',
                        'Explicit release and backup manifests are required.',
                    );
                }
            }
            $this->assertExactModuleState($registry);
            $this->assertDefinitionState($registry);

            return ['modules' => $registry->moduleKeys(), 'applied_module_migrations' => 0];
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array{modules: list<string>, applied_module_migrations: int} */
    public function installEmptyDatabase(): array
    {
        if (!$this->acquireLock()) {
            throw new ModuleException('MODULE_UPGRADE_LOCKED', 'Another upgrade is already running.');
        }
        try {
            if (!$this->databaseIsEmpty()) {
                throw new ModuleException(
                    'INSTALL_DATABASE_NOT_EMPTY',
                    'A fresh install requires an empty database.',
                );
            }
            (new TargetMigrationInventory())->scan($this->root);

            return $this->runLocked();
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array{modules: list<string>, applied_module_migrations: int} */
    public function run(UpgradePlan $plan): array
    {
        if (!$this->acquireLock()) {
            throw new ModuleException('MODULE_UPGRADE_LOCKED', 'Another upgrade is already running.');
        }
        try {
            $plan->assertInternallyConsistent();
            (new RepositoryUpgradeTargetVerifier())->verify($this->root, $plan);
            $this->assertSourceMigrationState($plan->sourceMigrations);

            return $this->runLocked();
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array{modules: list<string>, applied_module_migrations: int} */
    private function runLocked(): array
    {
        $registry = $this->registry();
        $plans = [];
        foreach ($registry->modules as $module) {
            $plans[] = $this->modulePlan($module);
        }
        if ($this->tableExists('pa_module_migration')) {
            foreach ($plans as $plan) {
                $this->assertModulePlan($plan);
            }
        }
        $this->migratePackage(
            $this->kernelMigrationsPath(),
            'kernel',
            'pa_kernel_migration',
        );
        $this->migratePackage(
            $this->dataPermissionMigrationsPath(),
            'data_permission',
            'pa_data_permission_migration',
        );

        foreach ($plans as $plan) {
            $this->assertModulePlan($plan);
        }

        $batchStatement = $this->pdo
            ->query('SELECT COALESCE(MAX(batch_no), 0) + 1 FROM pa_module_migration');
        if ($batchStatement === false) {
            throw new RuntimeException('MODULE_MIGRATION_LEDGER_UNAVAILABLE: batch could not be allocated.');
        }
        $batch = (int) $batchStatement->fetchColumn();
        $applied = 0;
        foreach ($plans as $index => $plan) {
            $applied += $this->applyModulePlan($registry->modules[$index], $plan, $batch);
        }
        (new ModuleAuthorizationCatalogSynchronizer(
            new PdoAuthorizationCatalogRepository($this->pdo),
        ))->synchronize($registry);
        (new MenuCatalogSynchronizer(new PdoMenuCatalogRepository($this->pdo)))->synchronize($registry);
        if ($this->tableExists('pa_setting_definition')) {
            SettingsRuntimeFactory::synchronizeDefinitions($this->pdo, $registry, new DateTimeImmutable('now'));
        }
        if ($this->tableExists('pa_reference_code_set')) {
            ReferenceCodeRuntimeFactory::synchronizeDefinitions($this->pdo, $registry, new DateTimeImmutable('now'));
        }

        return [
            'modules' => $registry->moduleKeys(),
            'applied_module_migrations' => $applied,
        ];
    }

    private function acquireLock(): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:lock_key, 0)');
        $statement->execute(['lock_key' => $this->lockKey()]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function releaseLock(): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_key)');
        $statement->execute(['lock_key' => $this->lockKey()]);
    }

    private function lockKey(): string
    {
        $statement = $this->pdo->query('SELECT DATABASE()');
        if ($statement === false) {
            throw new RuntimeException('DATABASE_CONTEXT_UNAVAILABLE: current database could not be read.');
        }

        return 'pa:upgrade:' . substr(hash('sha256', (string) $statement->fetchColumn()), 0, 48);
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

    private function databaseIsEmpty(): bool
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
SQL);
        if ($statement === false) {
            throw new ModuleException('INSTALL_DATABASE_STATE_UNAVAILABLE', 'Database state could not be read.');
        }

        return (int) $statement->fetchColumn() === 0;
    }

    private function assertPackageMigrationCurrent(string $path, string $table): void
    {
        if (!$this->tableExists($table)) {
            throw new ModuleException(
                'UPGRADE_EVIDENCE_REQUIRED',
                'Explicit release and backup manifests are required.',
            );
        }
        $files = glob($path . '/*.php');
        if ($files === false) {
            throw new ModuleException('PACKAGE_MIGRATION_SCAN_FAILED', 'Package migration inventory failed.');
        }
        $targetVersions = [];
        foreach ($files as $file) {
            if (preg_match('/\/(\d{14})_[a-z0-9_]+\.php$/D', $file, $matches) !== 1) {
                throw new ModuleException('PACKAGE_MIGRATION_SCAN_FAILED', 'Package migration inventory failed.');
            }
            $targetVersions[] = $matches[1];
        }
        sort($targetVersions, SORT_STRING);

        $statement = $this->pdo->query("SELECT version, breakpoint FROM `{$table}`");
        if ($statement === false) {
            throw new ModuleException('PACKAGE_MIGRATION_LEDGER_UNAVAILABLE', 'Package migration ledger failed.');
        }
        $appliedVersions = [];
        while (($row = $statement->fetch()) !== false) {
            if ((int) $row['breakpoint'] !== 0) {
                throw new ModuleException(
                    'UPGRADE_EVIDENCE_REQUIRED',
                    'Explicit release and backup manifests are required.',
                );
            }
            $appliedVersions[] = (string) $row['version'];
        }
        sort($appliedVersions, SORT_STRING);
        if ($targetVersions !== $appliedVersions) {
            throw new ModuleException(
                'UPGRADE_EVIDENCE_REQUIRED',
                'Explicit release and backup manifests are required.',
            );
        }
    }

    private function assertSourceMigrationState(MigrationInventory $expected): void
    {
        $packageVersions = ['kernel' => [], 'data-permission' => []];
        $moduleMigrations = [];
        foreach ($expected->entries as $entry) {
            if (array_key_exists($entry['owner'], $packageVersions)) {
                if (preg_match('/^(\d{14})_[a-z0-9_]+$/D', $entry['key'], $matches) !== 1) {
                    throw new ModuleException(
                        'UPGRADE_SOURCE_DATABASE_MISMATCH',
                        'The database migration state does not match the release source.',
                    );
                }
                $packageVersions[$entry['owner']][] = $matches[1];
                continue;
            }
            if (!str_starts_with($entry['owner'], 'module:')) {
                throw new ModuleException(
                    'UPGRADE_SOURCE_DATABASE_MISMATCH',
                    'The database migration state does not match the release source.',
                );
            }
            $moduleKey = substr($entry['owner'], strlen('module:'));
            $moduleMigrations['module:' . $moduleKey . ':' . $entry['key']] = $entry['checksum'];
        }

        $this->assertPackageSourceVersions('pa_kernel_migration', $packageVersions['kernel']);
        $this->assertPackageSourceVersions('pa_data_permission_migration', $packageVersions['data-permission']);
        $this->assertModuleSourceMigrations($moduleMigrations);
    }

    private function assertExactModuleState(CompiledModuleRegistry $registry): void
    {
        $expectedInstallations = $registry->moduleKeys();
        sort($expectedInstallations, SORT_STRING);
        $statement = $this->pdo->query('SELECT module_key FROM pa_module_installation ORDER BY module_key');
        if ($statement === false) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current Module state is unavailable.');
        }
        $actualInstallations = array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        if ($actualInstallations !== $expectedInstallations) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current Module state differs from the release.');
        }

        $expectedLedgerModules = [];
        foreach ($registry->modules as $module) {
            $plan = $this->modulePlan($module);
            if ($plan['migrations'] !== []) {
                $expectedLedgerModules[] = $plan['module_key'];
            }
        }
        sort($expectedLedgerModules, SORT_STRING);
        $statement = $this->pdo->query(
            'SELECT DISTINCT module_key FROM pa_module_migration ORDER BY module_key',
        );
        if ($statement === false) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current Module ledger is unavailable.');
        }
        $actualLedgerModules = array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        if ($actualLedgerModules !== $expectedLedgerModules) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current Module ledger differs from the release.');
        }
    }

    private function assertDefinitionState(CompiledModuleRegistry $registry): void
    {
        $expectedSettings = [];
        foreach (SettingsRuntimeFactory::definitionRegistry($registry)->all() as $definition) {
            $expectedSettings[$definition->qualifiedKey()] = $definition->digest . ':active';
        }
        $this->assertDefinitionRows(
            'pa_setting_definition',
            'SELECT module_key, setting_key AS definition_key, definition_digest, status AS lifecycle'
                . " FROM pa_setting_definition WHERE status = 'active'",
            $expectedSettings,
        );

        $expectedReferenceCodes = [];
        foreach (ReferenceCodeRuntimeFactory::definitionRegistry($registry)->all() as $definition) {
            $expectedReferenceCodes[$definition->qualifiedKey()] = $definition->digest . ':active';
        }
        $this->assertDefinitionRows(
            'pa_reference_code_set',
            'SELECT module_key, set_key AS definition_key, definition_digest, lifecycle'
                . " FROM pa_reference_code_set WHERE lifecycle = 'active'",
            $expectedReferenceCodes,
        );
    }

    /** @param array<string, string> $expected */
    private function assertDefinitionRows(string $table, string $sql, array $expected): void
    {
        if (!$this->tableExists($table)) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current definition state is unavailable.');
        }
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current definition state is unavailable.');
        }
        $actual = [];
        while (($row = $statement->fetch()) !== false) {
            $identity = (string) $row['module_key'] . ':' . (string) $row['definition_key'];
            $actual[$identity] = (string) $row['definition_digest'] . ':' . (string) $row['lifecycle'];
        }
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        if ($actual !== $expected) {
            throw new ModuleException('UPGRADE_EVIDENCE_REQUIRED', 'Current definitions differ from the release.');
        }
    }

    /** @param list<string> $expectedVersions */
    private function assertPackageSourceVersions(string $table, array $expectedVersions): void
    {
        sort($expectedVersions, SORT_STRING);
        if (!$this->tableExists($table)) {
            if ($expectedVersions === []) {
                return;
            }
            $this->sourceDatabaseMismatch();
        }
        $statement = $this->pdo->query("SELECT version, breakpoint FROM `{$table}`");
        if ($statement === false) {
            $this->sourceDatabaseMismatch();
        }
        $actualVersions = [];
        while (($row = $statement->fetch()) !== false) {
            if ((int) $row['breakpoint'] !== 0) {
                $this->sourceDatabaseMismatch();
            }
            $actualVersions[] = (string) $row['version'];
        }
        sort($actualVersions, SORT_STRING);
        if ($actualVersions !== $expectedVersions) {
            $this->sourceDatabaseMismatch();
        }
    }

    /** @param array<string, string> $expected */
    private function assertModuleSourceMigrations(array $expected): void
    {
        if (!$this->tableExists('pa_module_migration')) {
            if ($expected === []) {
                return;
            }
            $this->sourceDatabaseMismatch();
        }
        $statement = $this->pdo->query(<<<'SQL'
SELECT module_key, migration_key, checksum, status FROM pa_module_migration
SQL);
        if ($statement === false) {
            $this->sourceDatabaseMismatch();
        }
        $actual = [];
        while (($row = $statement->fetch()) !== false) {
            if ((string) $row['status'] !== 'applied') {
                $this->sourceDatabaseMismatch();
            }
            $moduleKey = (string) $row['module_key'];
            $migrationKey = (string) $row['migration_key'];
            $prefix = $moduleKey . ':';
            if (!str_starts_with($migrationKey, $prefix)) {
                $this->sourceDatabaseMismatch();
            }
            $identity = 'module:' . $moduleKey . ':' . substr($migrationKey, strlen($prefix));
            $actual[$identity] = (string) $row['checksum'];
        }
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        if ($actual !== $expected) {
            $this->sourceDatabaseMismatch();
        }
    }

    private function sourceDatabaseMismatch(): never
    {
        throw new ModuleException(
            'UPGRADE_SOURCE_DATABASE_MISMATCH',
            'The database migration state does not match the release source.',
        );
    }

    private function registry(): CompiledModuleRegistry
    {
        $repositoryRoot = realpath($this->root);
        $configPath = $this->root . '/backend/config/modules.php';
        $physicalConfig = realpath($configPath);
        if ($repositoryRoot === false
            || $physicalConfig !== $repositoryRoot . '/backend/config/modules.php'
            || is_link($configPath)
            || !is_file($configPath)) {
            throw new ModuleException('MODULE_CONFIG_UNSAFE', 'Module configuration path is unsafe.');
        }
        $config = require $physicalConfig;
        $kernelVersion = is_array($config) ? ($config['kernel_version'] ?? null) : null;
        $configuredRoots = is_array($config) ? ($config['roots'] ?? null) : null;
        $frontendComponents = is_array($config) ? ($config['frontend_components'] ?? null) : null;
        if (!is_string($kernelVersion)
            || !is_array($configuredRoots)
            || !is_array($frontendComponents)) {
            throw new ModuleException('MODULE_CONFIG_UNSAFE', 'Module configuration is invalid.');
        }
        $roots = [];
        foreach ($configuredRoots as $path) {
            if (!is_string($path) || $path === '') {
                throw new ModuleException('MODULE_CONFIG_UNSAFE', 'Module configuration is invalid.');
            }
            $roots[] = $this->root . '/' . ltrim($path, '/');
        }
        $components = [];
        foreach ($frontendComponents as $component) {
            if (!is_string($component) || $component === '') {
                throw new ModuleException('MODULE_CONFIG_UNSAFE', 'Module configuration is invalid.');
            }
            $components[] = $component;
        }

        return (new ModuleRegistryFactory(
            $roots,
            $components,
            $kernelVersion,
            $this->packagePath(KernelPackage::NAME) . '/kernel/resources/schemas/module-manifest.schema.json',
        ))->compileAndCheckBoundaries();
    }

    private function migratePackage(string $path, string $environment, string $table): void
    {
        $this->manager($path, $environment, $table)->migrate($environment);
    }

    /**
     * @return array{
     *   module_key: string,
     *   module_version: string,
     *   migrations: list<array{key: string, checksum: string, migration: MigrationInterface}>
     * }
     */
    private function modulePlan(ManifestDocument $module): array
    {
        $moduleKey = $this->manifestString($module, 'key');
        $moduleVersion = $this->manifestString($module, 'version');
        $backend = $module->data['backend'] ?? [];
        $relativePath = is_array($backend) ? ($backend['migrations'] ?? null) : null;
        if ($relativePath === null) {
            return ['module_key' => $moduleKey, 'module_version' => $moduleVersion, 'migrations' => []];
        }
        if (!is_string($relativePath) || $relativePath === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', "Invalid migration path for {$moduleKey}.");
        }
        $path = $module->root . '/' . $relativePath;
        if (!is_dir($path)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', "Migration directory is missing for {$moduleKey}.");
        }

        $files = glob($path . '/*.php');
        if ($files === false) {
            throw new RuntimeException("MODULE_MIGRATION_SCAN_FAILED: {$moduleKey}.");
        }
        $filesByVersion = [];
        foreach ($files as $file) {
            if (preg_match('/\/(\d{14})_[a-z0-9_]+\.php$/D', $file, $matches) !== 1) {
                throw new ModuleException('MODULE_MANIFEST_INVALID', "Invalid migration filename for {$moduleKey}.");
            }
            $filesByVersion[(int) $matches[1]] = $file;
        }

        $manager = $this->manager($path, 'module', 'pa_kernel_migration');
        $migrations = [];
        foreach ($manager->getMigrations('module') as $version => $migration) {
            $file = $filesByVersion[(int) $version] ?? throw new ModuleException(
                'MODULE_MANIFEST_INVALID',
                "Migration source is missing for {$moduleKey}:{$version}.",
            );
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                throw new RuntimeException("MODULE_MIGRATION_SCAN_FAILED: {$moduleKey}:{$basename}.");
            }
            $migrations[] = [
                'key' => $moduleKey . ':' . $basename,
                'checksum' => $checksum,
                'migration' => $migration,
            ];
        }

        return ['module_key' => $moduleKey, 'module_version' => $moduleVersion, 'migrations' => $migrations];
    }

    /**
     * @param array{
     *   module_key: string,
     *   module_version: string,
     *   migrations: list<array{key: string, checksum: string, migration: MigrationInterface}>
     * } $plan
     */
    private function assertModulePlan(array $plan): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT module_key, migration_key, module_version, checksum, status
FROM pa_module_migration WHERE module_key = :module_key
SQL);
        $statement->execute(['module_key' => $plan['module_key']]);
        $records = [];
        while (($row = $statement->fetch()) !== false) {
            $records[] = new MigrationRecord(
                (string) $row['module_key'],
                (string) $row['migration_key'],
                (string) $row['module_version'],
                (string) $row['checksum'],
                (string) $row['status'],
            );
        }
        $ledger = new ModuleMigrationLedger($records);
        $currentKeys = [];
        foreach ($plan['migrations'] as $migration) {
            $currentKeys[] = $migration['key'];
            $ledger->shouldApply($plan['module_key'], $migration['key'], $migration['checksum']);
        }
        foreach ($records as $record) {
            if ($record->status === 'applied' && !in_array($record->migrationKey, $currentKeys, true)) {
                throw new ModuleException(
                    'MODULE_MIGRATION_MISSING',
                    "Applied migration file is missing: {$record->migrationKey}",
                );
            }
        }
    }

    /**
     * @param array{
     *   module_key: string,
     *   module_version: string,
     *   migrations: list<array{key: string, checksum: string, migration: MigrationInterface}>
     * } $plan
     */
    private function applyModulePlan(ManifestDocument $module, array $plan, int $batch): int
    {
        $pending = array_values(array_filter(
            $plan['migrations'],
            fn(array $migration): bool => $this->migrationNeedsApply($plan['module_key'], $migration['key']),
        ));
        $installation = $this->installation($plan['module_key']);
        $metadataChanged = $installation === null
            || $installation['installed_version'] !== $plan['module_version']
            || $installation['manifest_digest'] !== $module->digest
            || $installation['status'] !== 'active';
        if ($pending === [] && !$metadataChanged) {
            return 0;
        }

        $this->markInstallationStarted($module, $installation !== null);
        try {
            foreach ($pending as $migration) {
                $this->applyMigration($plan, $migration, $batch);
            }
            $this->markInstallationActive($module, $installation !== null);
        } catch (Throwable $exception) {
            $this->markInstallationFailed($plan['module_key']);
            throw $exception;
        }

        return count($pending);
    }

    private function migrationNeedsApply(string $moduleKey, string $migrationKey): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT status FROM pa_module_migration
WHERE module_key = :module_key AND migration_key = :migration_key
SQL);
        $statement->execute(['module_key' => $moduleKey, 'migration_key' => $migrationKey]);

        return $statement->fetchColumn() !== 'applied';
    }

    /**
     * @param array{module_key: string, module_version: string, migrations: list<mixed>} $plan
     * @param array{key: string, checksum: string, migration: MigrationInterface} $entry
     */
    private function applyMigration(array $plan, array $entry, int $batch): void
    {
        $now = $this->now();
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_migration (
    module_key, migration_key, module_version, checksum, batch_no,
    status, started_at, finished_at, error_code
) VALUES (
    :module_key, :migration_key, :module_version, :checksum, :batch_no,
    'applying', :started_at, NULL, NULL
)
ON DUPLICATE KEY UPDATE
    module_version = VALUES(module_version), checksum = VALUES(checksum),
    batch_no = VALUES(batch_no), status = 'applying', started_at = VALUES(started_at),
    finished_at = NULL, error_code = NULL
SQL);
        $statement->execute([
            'module_key' => $plan['module_key'],
            'migration_key' => $entry['key'],
            'module_version' => $plan['module_version'],
            'checksum' => $entry['checksum'],
            'batch_no' => $batch,
            'started_at' => $now,
        ]);

        try {
            $this->executeMigration($entry['migration']);
            $finished = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_migration SET status = 'applied', finished_at = :finished_at, error_code = NULL
WHERE module_key = :module_key AND migration_key = :migration_key
SQL);
            $finished->execute([
                'finished_at' => $this->now(),
                'module_key' => $plan['module_key'],
                'migration_key' => $entry['key'],
            ]);
        } catch (Throwable $exception) {
            $failed = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_migration SET status = 'failed', finished_at = :finished_at,
error_code = 'MODULE_MIGRATION_FAILED'
WHERE module_key = :module_key AND migration_key = :migration_key
SQL);
            $failed->execute([
                'finished_at' => $this->now(),
                'module_key' => $plan['module_key'],
                'migration_key' => $entry['key'],
            ]);
            throw new ModuleException(
                'MODULE_MIGRATION_FAILED',
                "Migration failed: {$entry['key']}",
            );
        }
    }

    private function executeMigration(MigrationInterface $migration): void
    {
        $manager = $this->manager(
            $this->kernelMigrationsPath(),
            'runtime',
            'pa_kernel_migration',
        );
        $adapter = $manager->getEnvironment('runtime')->getAdapter();
        $migration->setAdapter($adapter);
        $migration->setMigratingUp(true);
        $migration->preFlightCheck();
        if (method_exists($migration, MigrationInterface::INIT)) {
            $migration->{MigrationInterface::INIT}();
        }
        $transactional = $adapter->hasTransactions();
        if ($transactional) {
            $adapter->beginTransaction();
        }
        try {
            if (method_exists($migration, MigrationInterface::CHANGE)) {
                $migration->{MigrationInterface::CHANGE}();
            } else {
                $up = [$migration, MigrationInterface::UP];
                if (!is_callable($up)) {
                    throw new RuntimeException('MODULE_MIGRATION_INVALID: up() is missing.');
                }
                $up();
            }
            if ($transactional) {
                $adapter->commitTransaction();
            }
        } catch (Throwable $exception) {
            if ($transactional) {
                $adapter->rollbackTransaction();
            }
            throw $exception;
        }
        $migration->postFlightCheck();
    }

    /** @return array{installed_version: string, manifest_digest: string, status: string}|null */
    private function installation(string $moduleKey): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT installed_version, manifest_digest, status
FROM pa_module_installation WHERE module_key = :module_key
SQL);
        $statement->execute(['module_key' => $moduleKey]);
        $row = $statement->fetch();

        return $row === false ? null : [
            'installed_version' => (string) $row['installed_version'],
            'manifest_digest' => (string) $row['manifest_digest'],
            'status' => (string) $row['status'],
        ];
    }

    private function markInstallationStarted(ManifestDocument $module, bool $upgrade): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (
    module_key, installed_version, manifest_schema_version, manifest_digest,
    status, revision, created_at, updated_at
) VALUES (
    :module_key, :installed_version, :schema_version, :manifest_digest,
    'installing', 1, :created_at, :updated_at
)
ON DUPLICATE KEY UPDATE
    status = 'upgrading', revision = revision + 1,
    manifest_schema_version = VALUES(manifest_schema_version),
    manifest_digest = VALUES(manifest_digest), updated_at = VALUES(updated_at),
    last_error_code = NULL
SQL);
        $now = $this->now();
        $statement->execute([
            'module_key' => $this->manifestString($module, 'key'),
            'installed_version' => $this->manifestString($module, 'version'),
            'schema_version' => (int) $module->data['schema_version'],
            'manifest_digest' => $module->digest,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function markInstallationActive(ManifestDocument $module, bool $upgrade): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_installation SET
    installed_version = :installed_version, manifest_digest = :manifest_digest,
    status = 'active', installed_at = COALESCE(installed_at, :installed_at),
    activated_at = COALESCE(activated_at, :activated_at),
    upgraded_at = CASE WHEN :is_upgrade = 1 THEN :upgraded_at ELSE upgraded_at END,
    last_error_code = NULL, updated_at = :updated_at
WHERE module_key = :module_key
SQL);
        $now = $this->now();
        $statement->execute([
            'installed_version' => $this->manifestString($module, 'version'),
            'manifest_digest' => $module->digest,
            'installed_at' => $now,
            'activated_at' => $now,
            'is_upgrade' => $upgrade ? 1 : 0,
            'upgraded_at' => $now,
            'updated_at' => $now,
            'module_key' => $this->manifestString($module, 'key'),
        ]);
    }

    private function markInstallationFailed(string $moduleKey): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_installation SET status = 'failed', last_error_code = 'MODULE_MIGRATION_FAILED',
revision = revision + 1, updated_at = :updated_at WHERE module_key = :module_key
SQL);
        $statement->execute(['updated_at' => $this->now(), 'module_key' => $moduleKey]);
    }

    private function manager(string $path, string $environment, string $table): Manager
    {
        $databaseStatement = $this->pdo->query('SELECT DATABASE()');
        if ($databaseStatement === false) {
            throw new RuntimeException('DATABASE_CONTEXT_UNAVAILABLE: current database could not be read.');
        }

        return new Manager(new Config([
            'paths' => ['migrations' => $path],
            'environments' => [
                'default_environment' => $environment,
                'default_migration_table' => $table,
                $environment => [
                    'adapter' => 'mysql',
                    'connection' => $this->pdo,
                    'name' => (string) $databaseStatement->fetchColumn(),
                    'migration_table' => $table,
                ],
            ],
            'version_order' => Config::VERSION_ORDER_CREATION_TIME,
        ]), new Input([]), new NullOutput());
    }

    private function manifestString(ManifestDocument $module, string $key): string
    {
        $value = $module->data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', "Missing manifest field: {$key}.");
        }

        return $value;
    }

    private function packagePath(string $package): string
    {
        $path = InstalledVersions::getInstallPath($package);
        if (!is_string($path) || $path === '') {
            throw new RuntimeException("PACKAGE_INSTALL_PATH_UNAVAILABLE: {$package}.");
        }

        return rtrim($path, '/');
    }

    private function kernelMigrationsPath(): string
    {
        return $this->packagePath(KernelPackage::NAME) . '/kernel/database/migrations';
    }

    private function dataPermissionMigrationsPath(): string
    {
        return $this->packagePath(DataPermissionPackage::NAME) . '/data-permission/database/migrations';
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }

    private static function connectFromEnvironment(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_DATABASE') ?: 'peanut_admin',
            ),
            getenv('DB_USERNAME') ?: 'peanut_admin',
            getenv('DB_PASSWORD') ?: 'peanut_admin_dev',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
