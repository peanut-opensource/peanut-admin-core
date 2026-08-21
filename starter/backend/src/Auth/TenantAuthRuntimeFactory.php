<?php

declare(strict_types=1);

namespace PeanutAdmin\InternalStarter\Auth;

use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantClientRegistry;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use RuntimeException;

final readonly class TenantAuthRuntimeFactory
{
    private TenantClientRegistry $clients;

    public function __construct(
        private PDO $pdo,
        private PasswordHasher $passwords,
        string $root,
        private string $identifierHmacKey,
    ) {
        if (strlen($identifierHmacKey) < 32) {
            throw new RuntimeException('Starter identifier HMAC key is too short.');
        }
        $config = require $root . '/backend/config/auth.php';
        $clientKeys = $config['tenant_clients'] ?? null;
        if (!is_array($clientKeys) || !array_is_list($clientKeys)) {
            throw new RuntimeException('Starter Tenant Client configuration is invalid.');
        }
        $this->clients = new TenantClientRegistry(array_map('strval', $clientKeys));
    }

    public function create(string $clientKey): TenantAuthService
    {
        return new TenantAuthService(
            new PdoTransactionManager($this->pdo),
            new PdoTenantAuthRepository($this->pdo),
            $this->passwords,
            new SystemClock(),
            new TokenIssuer(),
            $this->identifierHmacKey,
            $this->clients,
            $clientKey,
        );
    }
}
