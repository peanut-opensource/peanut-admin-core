<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\InternalStarter\Module\ComposerVersionConstraintMatcher;
use PeanutAdmin\InternalStarter\Module\OpisManifestSchemaValidator;
use PeanutAdmin\InternalStarter\Module\ReflectionContractInspector;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Package as KernelPackage;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Settings\Application\SettingResolver;
use PeanutAdmin\Settings\Cache\ArrayRevisionedSettingCache;
use PeanutAdmin\Settings\Database\Schema as SettingsSchema;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use PeanutAdmin\Settings\Package as SettingsPackage;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;
use PeanutAdmin\Settings\Secret\SecretProtector;
use PeanutAdmin\Settings\Secret\SecretStorageContext;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use think\console\Input;
use think\migration\NullOutput;

$root = dirname(__DIR__, 2);
$required = [
    'backend/config/modules.php',
    'backend/src/Modules/Example/Greeting/Resources/setting-definitions.json',
    'backend/src/Modules/Peanut/Settings/module.json',
    'backend/src/Modules/Peanut/Settings/ModuleProvider.php',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        fwrite(STDERR, "ERROR: starter Settings host file is missing: {$path}\n");
        exit(1);
    }
}

$requiredPort = static function (string $name): int {
    $value = getenv($name);
    if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
        fwrite(STDERR, "ERROR: {$name} is required for starter Settings verification\n");
        exit(1);
    }
    $port = (int) $value;
    if ($port < 1 || $port > 65535) {
        fwrite(STDERR, "ERROR: {$name} must be an integer between 1 and 65535\n");
        exit(1);
    }

    return $port;
};
$requiredPort('MYSQL_PORT');
$databasePort = $requiredPort('DB_PORT');

require $root . '/backend/vendor/autoload.php';

if (!class_exists(SettingsPackage::class)) {
    fwrite(STDERR, "ERROR: starter does not consume peanut-admin/core through Composer\n");
    exit(1);
}

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};

$databaseName = 'peanut_admin_starter_settings_' . getmypid();
$rootCredential = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$dsn = "mysql:host=127.0.0.1;port={$databasePort};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$admin = new PDO($dsn, 'root', $rootCredential, $options);
$admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
$admin->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

$migrate = static function (string $path, string $table) use (
    $databaseName,
    $rootCredential,
    $databasePort,
): void {
    $config = new Config([
        'paths' => ['migrations' => $path],
        'environments' => [
            'default_environment' => 'starter',
            'default_migration_table' => $table,
            'starter' => [
                'adapter' => 'mysql',
                'host' => '127.0.0.1',
                'port' => $databasePort,
                'name' => $databaseName,
                'user' => 'root',
                'pass' => $rootCredential,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
            ],
        ],
        'version_order' => Config::VERSION_ORDER_CREATION_TIME,
    ]);
    (new Manager($config, new Input([]), new NullOutput()))->migrate('starter');
};

try {
    $kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
    $corePackageRoot = InstalledVersions::getInstallPath(SettingsPackage::NAME);
    if (!is_string($kernelRoot) || !is_string($corePackageRoot)) {
        throw new RuntimeException('Starter package installation paths are unavailable.');
    }
    $kernelRoot .= '/kernel';
    $settingsPackageRoot = $corePackageRoot . '/settings';
    $assertSame('0.1.0', SettingsPackage::VERSION, 'Unexpected Settings package version.');
    $installedCoreRoot = realpath($corePackageRoot);
    $installedSettingsRoot = realpath($settingsPackageRoot);
    $vendorRoot = realpath($root . '/backend/vendor');
    if (!is_string($installedCoreRoot)
        || !is_string($installedSettingsRoot)
        || !is_string($vendorRoot)
        || !str_starts_with($installedCoreRoot, $vendorRoot . DIRECTORY_SEPARATOR)
        || !str_starts_with($installedSettingsRoot, $installedCoreRoot . DIRECTORY_SEPARATOR)
        || !is_file($corePackageRoot . '/composer.json')
        || !is_file($settingsPackageRoot . '/src/Package.php')) {
        throw new RuntimeException('Settings must resolve through the installed core package root.');
    }

    $settingsModuleRoot = $root . '/backend/src/Modules/Peanut/Settings';
    $migrate($kernelRoot . '/database/migrations', 'pa_kernel_migration');
    $migrate($settingsModuleRoot . '/Database/Migrations', 'pa_settings_migration');
    $migrate($settingsModuleRoot . '/Database/Migrations', 'pa_settings_migration');

    $pdo = new PDO($dsn . ";dbname={$databaseName}", 'root', $rootCredential, $options);
    $assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM pa_settings_migration')->fetchColumn(), 'Settings migration order is incomplete.');
    foreach (SettingsSchema::tableNames() as $table) {
        $statement = $pdo->prepare(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = :table_name
SQL);
        $statement->execute(['table_name' => $table]);
        $assertSame(1, (int) $statement->fetchColumn(), "Missing Settings table: {$table}");
    }

    $moduleConfig = require $root . '/backend/config/modules.php';
    $layout = new ModuleHostLayout(
        'backend/src/Modules',
        'ExampleHost\\App\\Modules',
        'frontend/src/modules',
    );
    $manifestLoader = new ManifestLoader();
    $documents = array_map(
        static fn(string $path) => $manifestLoader->load($root . '/' . $path),
        $moduleConfig['roots'],
    );
    $moduleRegistry = (new ModuleRegistryCompiler(
        new OpisManifestSchemaValidator($kernelRoot . '/resources/schemas/module-manifest.schema.json'),
        new ComposerVersionConstraintMatcher(),
        new ReflectionContractInspector(),
        $moduleConfig['kernel_version'],
        $moduleConfig['frontend_components'],
        $layout,
        [
            ...KernelSchema::tableNames(),
            ...AuthorizationSchema::tableNames(),
            ...ModuleSchema::tableNames(),
            ...IdempotencySchema::tableNames(),
            ...DataPermissionSchema::tableNames(),
        ],
        $moduleConfig['registered_client_keys'],
    ))->compile($documents);
    (new ModuleBoundaryChecker($moduleRegistry, $layout, ['pa_', 'starter_']))->check();
    $assertSame(
        ['example.greeting', 'peanut.file-media', 'peanut.task-job', 'peanut.import-export', 'peanut.integration-security', 'peanut.notification-sms', 'peanut.reference-codes', 'peanut.settings'],
        $moduleRegistry->moduleKeys(),
        'Starter Modules did not compile.',
    );
    foreach (SettingsSchema::tableNames() as $table) {
        $assertSame('peanut.settings', $moduleRegistry->ownedTableOwners[$table] ?? null, "Settings table owner is invalid: {$table}");
    }

    $definitionLoader = new SettingDefinitionLoader();
    $definitionRegistry = new SettingDefinitionRegistry();
    foreach ($documents as $document) {
        $moduleKey = $document->data['key'];
        $resource = $document->data['backend']['setting_definitions'] ?? null;
        $definitions = is_string($resource)
            ? $definitionLoader->load($moduleKey, $document->root . '/' . $resource)
            : [];
        $definitionRegistry->registerModule($moduleKey, $definitions);
    }
    $repository = new PdoSettingRepository($pdo);
    $now = new DateTimeImmutable('2026-07-20T00:00:00.000Z', new DateTimeZone('UTC'));
    $assertSame(
        ['inserted' => 1, 'updated' => 0, 'retired' => 0],
        $repository->synchronize($definitionRegistry, $now),
        'Starter definition synchronization did not insert the fictional definition.',
    );
    $assertSame(
        ['inserted' => 0, 'updated' => 0, 'retired' => 0],
        $repository->synchronize($definitionRegistry, $now),
        'Repeated starter definition synchronization must be idempotent.',
    );

    $protector = new class implements SecretProtector {
        public function protect(string $plaintext, SecretStorageContext $context): array
        {
            throw new RuntimeException('Starter fixture has no secret definitions.');
        }

        public function reveal(
            string $ciphertext,
            string $nonce,
            string $keyId,
            SecretStorageContext $context,
        ): string {
            throw new RuntimeException('Starter fixture has no secret definitions.');
        }
    };
    $definition = $definitionRegistry->require('example.greeting', 'display-style');
    $resolved = (new SettingResolver(
        $repository,
        $protector,
        new ArrayRevisionedSettingCache(),
    ))->resolveDeployment($definition, $now);
    $assertSame('friendly', $resolved->value, 'Fictional default setting did not resolve.');
    $assertSame('default', $resolved->source, 'Fictional setting source is invalid.');
    $assertSame(1, $resolved->revision, 'Fictional definition revision is invalid.');
    $assertSame(null, $resolved->etag, 'A default-only setting must not fabricate an ETag.');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
}

fwrite(STDOUT, "Internal starter Settings integration: OK\n");
