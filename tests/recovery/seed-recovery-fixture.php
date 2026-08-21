<?php

declare(strict_types=1);

use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallProductProfileApplier;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$pdo = new PDO(
    sprintf(
        'mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4',
        (int) (getenv('MYSQL_PORT') ?: 3306),
        getenv('DB_DATABASE') ?: 'peanut_admin_recovery_source',
    ),
    getenv('DB_USERNAME') ?: 'root',
    getenv('DB_PASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

$operatorId = (int) $pdo->query('SELECT id FROM pa_platform_operator ORDER BY id LIMIT 1')->fetchColumn();
$bootstrap = new BootstrapService(
    new PdoTransactionManager($pdo),
    new PdoIdentityRepository($pdo),
    new PdoTenantRepository($pdo),
    new PdoMembershipRepository($pdo),
    new PdoPlatformRepository($pdo),
    new PdoAuditRepository($pdo),
    new PasswordHasher(),
);
$beta = $bootstrap->provisionTenantOwnerCandidate(
    $operatorId,
    'beta',
    'Beta Fixture',
    'owner@example.test',
    null,
    'Beta Owner',
    'recovery-beta-create',
);
$bootstrap->activateTenantOwner(
    $operatorId,
    $beta->tenantId,
    $beta->memberId,
    'recovery-beta-owner-activate',
);
$bootstrap->activateTenant($operatorId, $beta->tenantId, 'recovery-beta-activate');

$profile = InstallProductProfile::load(
    $root . '/profiles/reference-admin.json',
    $root . '/schemas/product-profile.schema.json',
);
(new InstallProductProfileApplier($root, $pdo))->apply($beta->tenantId, $profile);

$alphaId = (int) $pdo->query("SELECT id FROM pa_tenant WHERE code = 'alpha'")->fetchColumn();
$now = gmdate('Y-m-d H:i:s.000');
$statement = $pdo->prepare(<<<'SQL'
INSERT INTO pa_example_project (tenant_id, code, name, status, created_at, updated_at)
VALUES (:tenant_id, :code, :name, 'active', :created_at, :updated_at)
SQL);
foreach ([
    [$alphaId, 'alpha-project', 'Alpha Project'],
    [$beta->tenantId, 'beta-project', 'Beta Project'],
] as [$tenantId, $code, $name]) {
    $statement->execute([
        'tenant_id' => $tenantId,
        'code' => $code,
        'name' => $name,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

fwrite(STDOUT, "Recovery fixture: OK\n");
