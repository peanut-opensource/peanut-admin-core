<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantClientRegistry;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use RuntimeException;
use think\Container;

final class TenantAuthRuntimeFactory
{
    private function __construct() {}

    public static function create(?string $clientKey = null, ?PDO $pdo = null): TenantAuthService
    {
        $hmacKey = getenv('AUTH_IDENTIFIER_HMAC_KEY');
        if (!is_string($hmacKey) || strlen($hmacKey) < 32) {
            throw new RuntimeException('AUTH_IDENTIFIER_HMAC_KEY must contain at least 32 bytes.');
        }

        $pdo ??= Container::getInstance()->make(PDO::class);

        $config = self::config();
        $clientKey ??= $config['default_client'];

        return new TenantAuthService(
            new PdoTransactionManager($pdo),
            new PdoTenantAuthRepository($pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            $hmacKey,
            new TenantClientRegistry($config['clients']),
            $clientKey,
        );
    }

    /** @return array{clients: non-empty-list<string>, default_client: string} */
    private static function config(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/auth.php';
        $tenant = is_array($config['tenant'] ?? null) ? $config['tenant'] : [];
        $clients = $tenant['clients'] ?? null;
        $default = $tenant['default_client'] ?? null;
        if (!is_array($clients) || $clients === [] || !array_is_list($clients) || !is_string($default)) {
            throw new RuntimeException('Tenant Client configuration is invalid.');
        }

        return [
            'clients' => array_map('strval', $clients),
            'default_client' => $default,
        ];
    }
}
