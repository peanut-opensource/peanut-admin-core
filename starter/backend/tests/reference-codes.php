<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use PeanutAdmin\InternalStarter\Module\ModuleRegistryFactory;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Package as KernelPackage;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeAdminService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeQuery;
use PeanutAdmin\ReferenceCodes\Database\Schema as ReferenceCodeSchema;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetLoader;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetRegistry;
use PeanutAdmin\ReferenceCodes\Package as ReferenceCodesPackage;
use PeanutAdmin\ReferenceCodes\Persistence\PdoReferenceCodeRepository;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use think\console\Input;
use think\migration\NullOutput;

$ports = [];
foreach (['MYSQL_PORT', 'DB_PORT'] as $name) {
    $value = getenv($name);
    if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
        fwrite(STDERR, "ERROR: {$name} is required for starter Reference Codes verification\n");
        exit(1);
    }
    $ports[$name] = (int) $value;
    if ($ports[$name] < 1 || $ports[$name] > 65535) {
        fwrite(STDERR, "ERROR: {$name} must be an integer between 1 and 65535\n");
        exit(1);
    }
}
if ($ports['MYSQL_PORT'] !== $ports['DB_PORT']) {
    fwrite(STDERR, "ERROR: MYSQL_PORT and DB_PORT must identify the same test service\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$databaseName = 'peanut_admin_starter_reference_codes_' . getmypid();
$rootCredential = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$port = $ports['DB_PORT'];
$dsn = "mysql:host=127.0.0.1;port={$port};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$admin = new PDO($dsn, 'root', $rootCredential, $options);
$definitionFixture = sys_get_temp_dir() . '/peanut-starter-reference-codes-' . bin2hex(random_bytes(8)) . '.json';

$migrate = static function (string $path, string $table) use (
    $databaseName,
    $rootCredential,
    $port,
): void {
    $config = new Config([
        'paths' => ['migrations' => $path],
        'environments' => [
            'default_environment' => 'starter',
            'default_migration_table' => $table,
            'starter' => [
                'adapter' => 'mysql',
                'host' => '127.0.0.1',
                'port' => $port,
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
    $admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    $admin->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
    $dataPermissionRoot = InstalledVersions::getInstallPath(DataPermissionPackage::NAME);
    $referenceCodesRoot = InstalledVersions::getInstallPath(ReferenceCodesPackage::NAME);
    if (!is_string($kernelRoot) || !is_string($dataPermissionRoot) || !is_string($referenceCodesRoot)) {
        throw new RuntimeException('Starter package installation paths are unavailable.');
    }
    $kernelRoot .= '/kernel';
    $dataPermissionRoot .= '/data-permission';
    $referenceCodesRoot .= '/reference-codes';
    $migrate($kernelRoot . '/database/migrations', 'pa_kernel_migration');
    $migrate($dataPermissionRoot . '/database/migrations', 'pa_data_permission_migration');

    $pdo = new PDO($dsn . ";dbname={$databaseName}", 'root', $rootCredential, $options);
    foreach (ReferenceCodeSchema::tableNames() as $table) {
        $pdo->exec(ReferenceCodeSchema::createSql($table));
    }
    $registry = (new ModuleRegistryFactory($root))->compile();
    if ($registry->moduleKeys() !== ['example.greeting', 'peanut.file-media', 'peanut.task-job', 'peanut.import-export', 'peanut.integration-security', 'peanut.notification-sms', 'peanut.reference-codes', 'peanut.settings']) {
        throw new RuntimeException('Starter reference-code Module was not compiled.');
    }
    $committedResource = $root
        . '/backend/src/Modules/Peanut/ReferenceCodes/Resources/reference-code-sets.json';
    if ((string) file_get_contents($committedResource) !== "[]\n") {
        throw new RuntimeException('Starter committed a reference-code set or value.');
    }

    $transactions = new PdoTransactionManager($pdo);
    $bootstrap = new BootstrapService(
        $transactions,
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher(),
    );
    $platform = $bootstrap->bootstrapPlatformOwner(
        'starter-reference-owner@example.test',
        'Starter-reference-password-2026!',
        'Starter Reference Owner',
        'starter-reference-platform',
    );
    $candidate = $bootstrap->provisionTenantOwnerCandidate(
        $platform->operatorId,
        'starter-reference',
        'Starter Reference Tenant',
        'starter-reference-owner@example.test',
        null,
        'Starter Reference Owner',
        'starter-reference-tenant',
    );
    $bootstrap->activateTenantOwner(
        $platform->operatorId,
        $candidate->tenantId,
        $candidate->memberId,
        'starter-reference-member-active',
    );
    $bootstrap->activateTenant(
        $platform->operatorId,
        $candidate->tenantId,
        'starter-reference-tenant-active',
    );

    file_put_contents($definitionFixture, json_encode([[
        'key' => 'synthetic-codes',
        'name' => 'Synthetic verification codes',
        'description' => 'Temporary generic declaration for starter package verification.',
    ]], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $definitions = (new ReferenceCodeSetLoader())->load('peanut.reference-codes', $definitionFixture);
    $definitionRegistry = new ReferenceCodeSetRegistry();
    $definitionRegistry->registerModule('peanut.reference-codes', $definitions);
    $repository = new PdoReferenceCodeRepository($pdo);
    $synchronized = $repository->synchronize(
        $definitionRegistry,
        new DateTimeImmutable('2020-01-01T00:00:00.000Z'),
    );
    if ($synchronized !== ['inserted' => 1, 'updated' => 0, 'retired' => 0, 'reactivated' => 0]) {
        throw new RuntimeException('Starter reference-code declaration did not synchronize exactly once.');
    }
    $context = TenantContext::fromValidatedSession(new ValidatedTenantSession(
        1,
        'starter-reference-session',
        $candidate->tenantId,
        $candidate->accountId,
        $candidate->memberId,
        'operations-web',
        new DateTimeImmutable('2020-01-01T00:00:00.000Z'),
        1,
    ), 'starter-reference-request');
    $definition = $definitions[0];
    $created = (new ReferenceCodeAdminService($repository))->create(
        $definition,
        $context,
        'synthetic-code',
        'Synthetic label',
        ['verified' => true],
        'active',
        0,
        new DateTimeImmutable('2020-01-01T00:00:00.000Z'),
        null,
        '*',
    );
    $active = (new ReferenceCodeQuery($repository))->listActiveCandidates($definition, $context);
    if ($created->code !== 'synthetic-code'
        || !$created->selectable()
        || count($active) !== 1
        || $active[0]->code !== 'synthetic-code') {
        throw new RuntimeException('Starter reference-code package round trip failed.');
    }
    foreach (['pa_reference_code_set', 'pa_reference_code_entry', 'pa_reference_code_entry_version'] as $table) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== 1) {
            throw new RuntimeException("Starter reference-code table count is invalid: {$table}.");
        }
    }
} finally {
    if (is_file($definitionFixture)) {
        unlink($definitionFixture);
    }
    $admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
}

fwrite(STDOUT, "Internal starter reference-code package integration: OK\n");
