<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use RuntimeException;
use think\Container;

final class PlatformAuthRuntimeFactory
{
    private function __construct() {}

    public static function create(?PDO $pdo = null): PlatformAuthService
    {
        $hmacKey = getenv('AUTH_IDENTIFIER_HMAC_KEY');
        if (!is_string($hmacKey) || strlen($hmacKey) < 32) {
            throw new RuntimeException('AUTH_IDENTIFIER_HMAC_KEY must contain at least 32 bytes.');
        }

        $pdo ??= Container::getInstance()->make(PDO::class);

        return new PlatformAuthService(
            new PdoTransactionManager($pdo),
            new PdoPlatformAuthRepository($pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            $hmacKey,
        );
    }
}
