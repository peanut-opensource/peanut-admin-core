<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\Persistence\ThinkPhpTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantClientRegistry;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Package as KernelPackage;
use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use think\App;
use think\console\Input;
use think\migration\NullOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$databaseName = 'peanut_admin_starter_' . getmypid();
$port = (int) (getenv('MYSQL_PORT') ?: 3306);
$rootCredential = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$dsn = "mysql:host=127.0.0.1;port={$port};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$admin = new PDO($dsn, 'root', $rootCredential, $options);
$admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
$admin->exec(
    "CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci",
);

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
    $kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
    $dataPermissionRoot = InstalledVersions::getInstallPath(DataPermissionPackage::NAME);
    if (!is_string($kernelRoot) || !is_string($dataPermissionRoot)) {
        throw new RuntimeException('Starter package installation paths are unavailable.');
    }
    $kernelRoot .= '/kernel';
    $dataPermissionRoot .= '/data-permission';
    $migrate($kernelRoot . '/database/migrations', 'pa_kernel_migration');
    $migrate($dataPermissionRoot . '/database/migrations', 'pa_data_permission_migration');
    $pdo = new PDO($dsn . ";dbname={$databaseName}", 'root', $rootCredential, $options);
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_PORT=' . $port);
    putenv('DB_DATABASE=' . $databaseName);
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=' . $rootCredential);
    (new App($root . '/backend'))->initialize();
    $passwords = new PasswordHasher();
    $bootstrap = new BootstrapService(passwords: $passwords);
    $email = 'owner@example.test';
    $plainPassword = 'starter-password-value-2026';
    $platform = $bootstrap->bootstrapPlatformOwner(
        $email,
        $plainPassword,
        'Starter Owner',
        'starter-platform',
    );
    $candidate = $bootstrap->provisionTenantOwnerCandidate(
        $platform->operatorId,
        'starter-tenant',
        'Starter Tenant',
        $email,
        null,
        'Starter Member',
        'starter-tenant-create',
    );
    $bootstrap->activateTenantOwner(
        $platform->operatorId,
        $candidate->tenantId,
        $candidate->memberId,
        'starter-member-activate',
    );
    $bootstrap->activateTenant(
        $platform->operatorId,
        $candidate->tenantId,
        'starter-tenant-activate',
    );

    $authConfig = require $root . '/backend/config/auth.php';
    $clientDefinitions = $authConfig['tenant_clients'] ?? null;
    if (!is_array($clientDefinitions) || !array_is_list($clientDefinitions)) {
        throw new RuntimeException('Starter Tenant Client configuration is invalid.');
    }
    $clientKeys = [];
    foreach ($clientDefinitions as $definition) {
        $clientKey = is_string($definition) ? $definition : ($definition['key'] ?? null);
        if (!is_string($clientKey) || $clientKey === '') {
            throw new RuntimeException('Starter Tenant Client configuration is invalid.');
        }
        $clientKeys[] = $clientKey;
    }
    $expectedClientKeys = ['operations-web', 'reporting-web'];
    $sortedClientKeys = $clientKeys;
    sort($sortedClientKeys, SORT_STRING);
    if ($sortedClientKeys !== $expectedClientKeys) {
        throw new RuntimeException('Starter Tenant Client configuration does not match the generated fixture.');
    }
    $clients = new TenantClientRegistry($clientKeys);
    $service = static fn(string $clientKey): TenantAuthService => new TenantAuthService(
        new ThinkPhpTenantAuthRepository(),
        $passwords,
        new SystemClock(),
        new TokenIssuer(),
        'starter-identifier-hmac-secret-at-least-32-bytes',
        $clients,
        $clientKey,
    );
    $operations = new TenantAuthEndpoint($service('operations-web'));
    $reporting = new TenantAuthEndpoint($service('reporting-web'));
    $login = static function (TenantAuthEndpoint $endpoint, string $requestId) use (
        $email,
        $plainPassword,
    ): array {
        $response = $endpoint->login(
            $email,
            $plainPassword,
            'starter-tenant',
            '127.0.0.1',
            'Starter Verification',
            $requestId,
        );
        $accessToken = $response->body['data']['access_token'] ?? null;
        $cookie = $response->headers['Set-Cookie'] ?? null;
        if (!is_string($accessToken) || !is_string($cookie)) {
            throw new RuntimeException('Starter authentication response is incomplete.');
        }
        foreach (['Secure', 'HttpOnly', 'SameSite=Lax', 'Path=/'] as $attribute) {
            if (!str_contains($cookie, $attribute)) {
                throw new RuntimeException("Starter refresh cookie is missing {$attribute}.");
            }
        }
        if (str_contains($cookie, 'Domain=')) {
            throw new RuntimeException('Starter refresh cookie must not contain Domain.');
        }
        [$pair] = explode(';', $cookie, 2);
        [$cookieName, $refreshToken] = explode('=', $pair, 2);

        return [
            'access_token' => $accessToken,
            'refresh_token' => rawurldecode($refreshToken),
            'cookie_name' => $cookieName,
        ];
    };
    $operationsAuth = $login($operations, 'starter-operations-login');
    $reportingAuth = $login($reporting, 'starter-reporting-login');

    if ($operationsAuth['cookie_name'] === $reportingAuth['cookie_name']) {
        throw new RuntimeException('Starter Tenant Client binding failed.');
    }
    try {
        $reporting->context($operationsAuth['access_token'], 'starter-cross-client-access');
        throw new RuntimeException('Cross-Client access token was accepted.');
    } catch (AuthException $exception) {
        if ($exception->errorCode !== 'AUTH_TOKEN_INVALID') {
            throw $exception;
        }
    }
    try {
        $reporting->refresh(
            $operationsAuth['refresh_token'],
            true,
            '127.0.0.1',
            'Starter Verification',
            'starter-cross-client-refresh',
        );
        throw new RuntimeException('Cross-Client refresh token was accepted.');
    } catch (AuthException $exception) {
        if ($exception->errorCode !== 'AUTH_TOKEN_INVALID') {
            throw $exception;
        }
    }
    $rotated = $operations->refresh(
        $operationsAuth['refresh_token'],
        true,
        '127.0.0.1',
        'Starter Verification',
        'starter-operations-refresh',
    );
    if (!str_starts_with(
        $rotated->headers['Set-Cookie'] ?? '',
        $operationsAuth['cookie_name'] . '=',
    )) {
        throw new RuntimeException('Starter refresh returned the wrong Client cookie.');
    }
    $reporting->context($reportingAuth['access_token'], 'starter-reporting-still-valid');

    $statement = $pdo->query('SELECT client_key FROM pa_tenant_session ORDER BY client_key');
    if ($statement === false) {
        throw new RuntimeException('Starter sessions are unavailable.');
    }
    $sessionClients = $statement->fetchAll(PDO::FETCH_COLUMN);
    if ($sessionClients !== ['operations-web', 'reporting-web']) {
        throw new RuntimeException('Starter session families were not isolated.');
    }
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
}

fwrite(STDOUT, "Internal starter Tenant Client integration: OK\n");
