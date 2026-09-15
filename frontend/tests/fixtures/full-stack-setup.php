<?php

declare(strict_types=1);

use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallProductProfileApplier;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\Tests\Support\ThinkPhpTestConnection;
use PeanutAdmin\Kernel\Authorization\CorePermissionCatalog;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Application\TenantOwnerAdminService;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;
use PeanutAdmin\Testing\Authorization\ThinkPhpAuthorizationFixtureSeeder;
use think\App;

$root = dirname(__DIR__, 3);
$requiredPort = static function (string $name): int {
    $value = getenv($name);
    if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
        fwrite(STDERR, "ERROR: {$name} is required for browser fixture setup\n");
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
$port = $requiredPort('DB_PORT');

require $root . '/vendor/autoload.php';

$database = getenv('DB_DATABASE') ?: 'peanut_admin_browser_test';
if (!preg_match('/^peanut_admin_browser_test(?:_[a-z0-9]+)?$/', $database)) {
    throw new RuntimeException('Browser fixture database name is not allowed.');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$command = $argv[1] ?? 'setup';
if ($command === 'drop') {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    exit(0);
}
if ($command !== 'setup') {
    throw new RuntimeException('Expected setup or drop.');
}

$browserPassword = getenv('PEANUT_BROWSER_PASSWORD');
if (!is_string($browserPassword) || $browserPassword === '') {
    throw new RuntimeException('PEANUT_BROWSER_PASSWORD is required.');
}

$admin->exec("DROP DATABASE IF EXISTS `{$database}`");
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

$profile = InstallProductProfile::load(
    $root . '/profiles/reference-admin.json',
    $root . '/schemas/product-profile.schema.json',
);
$email = 'browser-owner@example.test';
$connection = ThinkPhpTestConnection::fromPdo($pdo);
$installation = (new InstallWorkflow($root))->run(
    $profile,
    $email,
    $browserPassword,
    'Browser Platform Owner',
    [
        'code' => 'alpha',
        'name' => 'Alpha Team',
        'owner_email' => $email,
        'owner_name' => 'Browser Tenant Owner',
    ],
);

$operatorId = (int) $installation['platform']['operator_id'];
$platformRoleId = (int) $installation['platform']['role_id'];
$alphaTenantId = (int) $installation['tenant']['tenant_id'];
$alphaMemberId = (int) $installation['tenant']['owner_member_id'];

$app = new App($root . '/backend');
$app->initialize();
$actor = PlatformContext::fromTrustedAutomation(
    (int) $installation['platform']['account_id'],
    $operatorId,
    'operations-web',
    'browser-fixture-beta',
    new DateTimeImmutable('now', new DateTimeZone('UTC')),
);
$tenantAdmin = $app->make(PlatformTenantAdminService::class);
$betaTenant = $tenantAdmin->createTenant(
    $actor,
    'beta',
    'Beta Team',
    'Beta Team',
    'zh-cn',
    'Asia/Shanghai',
);
$betaTenantId = (int) $betaTenant['id'];
$ownerAdmin = $app->make(TenantOwnerAdminService::class);
$beta = $ownerAdmin->createCandidate(
    $actor,
    $betaTenantId,
    $email,
    'Browser Tenant Owner',
    null,
);
$betaMemberId = (int) $beta['member']['id'];
$ownerAdmin->activateCandidate(
    $actor,
    $betaTenantId,
    $betaMemberId,
    (int) $beta['member']['revision'],
    'browser-beta-owner-activate',
    'Activate the browser fixture owner.',
);
$tenantAdmin->transitionTenant(
    $actor,
    $betaTenantId,
    (int) $betaTenant['revision'],
    TenantStatus::Active,
    'Activate the browser fixture tenant.',
);
(new InstallProductProfileApplier($root))->apply($betaTenantId, $profile);

$platformGrant = $pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_platform_role_permission (platform_role_id, permission_id, granted_at)
SELECT :role_id, permission.id, UTC_TIMESTAMP(3)
FROM pa_permission permission
WHERE permission.`key` = :permission_key AND permission.status = 'active'
SQL);
foreach (CorePermissionCatalog::PLATFORM as $permissionKey) {
    $platformGrant->execute(['role_id' => $platformRoleId, 'permission_key' => $permissionKey]);
}

$seeder = new ThinkPhpAuthorizationFixtureSeeder();
$tenantPermissions = [
    ...CorePermissionCatalog::TENANT,
    'example.target.read',
    'example.target.manage',
    'example.reference.read',
    'example.reference.use',
    'example.work-item.read',
    'example.work-item.create',
    'example.work-item.update',
    'example.work-item.policy-publish',
    'peanut.reference-codes.read',
    'peanut.reference-codes.manage',
    'peanut.settings.read',
    'peanut.settings.manage',
];
foreach ([
    [$alphaTenantId, $alphaMemberId],
    [$betaTenantId, $betaMemberId],
] as [$tenantId, $memberId]) {
    $roleId = $seeder->roleForMember($tenantId, $memberId);
    $seeder->grantPermissions($tenantId, $roleId, $tenantPermissions);
}

$pdo->exec(<<<SQL
INSERT INTO pa_example_project (id, tenant_id, code, name, status, created_at, updated_at) VALUES
    (1001, {$alphaTenantId}, 'A', 'Project A', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (1002, {$alphaTenantId}, 'B', 'Project B', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (1003, {$alphaTenantId}, 'C', 'Project C', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (2001, {$betaTenantId}, 'A', 'Project A', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_example_reference_item
    (id, owner_type, owner_tenant_id, code, name, status, created_at, updated_at) VALUES
    (1001, 'deployment', NULL, 'public-ref', 'Public Reference', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (1002, 'tenant', {$alphaTenantId}, 'private-a', 'Private A', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (1003, 'tenant', {$alphaTenantId}, 'private-c', 'Private C', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_example_reference_scope
    (reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status) VALUES
    (1001, 'all_tenants', NULL, NULL, NULL, 'use', 'active'),
    (1002, 'typed_target', {$alphaTenantId}, 'example.project', '1001', 'use', 'active'),
    (1003, 'typed_target', {$alphaTenantId}, 'example.project', '1003', 'use', 'active');
INSERT INTO pa_example_work_item
    (id, tenant_id, project_id, queue_id, reference_item_id, owner_member_id, department_id,
     title, status, created_by_member_id, created_at, updated_at) VALUES
    (1001, {$alphaTenantId}, 1001, NULL, 1001, {$alphaMemberId}, NULL, 'Project A work', 'open', {$alphaMemberId}, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (1002, {$alphaTenantId}, 1002, NULL, 1001, {$alphaMemberId}, NULL, 'Project B work', 'open', {$alphaMemberId}, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);

$alphaRoleId = $seeder->roleForMember($alphaTenantId, $alphaMemberId);
$readProjects = $seeder->targetSet($alphaTenantId, $alphaMemberId, 'example.project', ['1001', '1002']);
$writeProjects = $seeder->targetSet($alphaTenantId, $alphaMemberId, 'example.project', ['1001']);
foreach ([
    ['example.reference-item', 'use', $readProjects],
    ['example.work-item', 'list', $readProjects],
    ['example.work-item', 'aggregate', $readProjects],
    ['example.work-item', 'create', $writeProjects],
    ['example.work-item', 'update', $writeProjects],
    ['example.work-item', 'policy-publish', $readProjects],
] as [$resourceKey, $operation, $targetSetId]) {
    $seeder->allowTargetGroups(
        $alphaTenantId,
        $alphaRoleId,
        $alphaMemberId,
        $resourceKey,
        $operation,
        [['example.project' => $targetSetId]],
    );
}

fwrite(STDOUT, json_encode([
    'status' => 'ready',
    'database' => $database,
    'tenants' => 2,
    'projects' => 4,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
