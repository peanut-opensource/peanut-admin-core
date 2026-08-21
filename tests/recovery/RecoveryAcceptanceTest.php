<?php

declare(strict_types=1);

namespace PeanutAdmin\Tests\Recovery;

use PDO;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\PdoTargetResolver;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantSelectionRequired;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PHPUnit\Framework\TestCase;

final class RecoveryAcceptanceTest extends TestCase
{
    private PDO $pdo;
    private TenantAuthService $auth;

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through scripts/test-recovery.');
        }
        $this->pdo = new PDO(
            sprintf(
                'mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4',
                (int) (getenv('MYSQL_PORT') ?: 3306),
                getenv('DB_DATABASE') ?: 'peanut_admin_recovery_target',
            ),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
        $this->auth = new TenantAuthService(
            new PdoTransactionManager($this->pdo),
            new PdoTenantAuthRepository($this->pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            'recovery-identifier-hmac-key-at-least-32-bytes',
        );
    }

    public function testRestoredFixtureRetainsSchemaRowsAndTenantLogin(): void
    {
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM pa_tenant'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM pa_example_project'));
        self::assertSame(10, $this->scalar(
            "SELECT COUNT(*) FROM pa_module_installation WHERE status = 'active'",
        ));
        self::assertSame(20, $this->scalar(
            "SELECT COUNT(*) FROM pa_tenant_module WHERE status = 'enabled'",
        ));

        $selection = $this->login(null);
        self::assertInstanceOf(TenantSelectionRequired::class, $selection);
        self::assertCount(2, $selection->tenants);

        $alpha = $this->login('alpha');
        $beta = $this->login('beta');
        self::assertInstanceOf(TenantAuthentication::class, $alpha);
        self::assertInstanceOf(TenantAuthentication::class, $beta);
        self::assertNotSame($alpha->context->tenantId, $beta->context->tenantId);
    }

    public function testRestoredTypedTargetsRemainTenantIsolated(): void
    {
        $alpha = $this->login('alpha');
        $beta = $this->login('beta');
        self::assertInstanceOf(TenantAuthentication::class, $alpha);
        self::assertInstanceOf(TenantAuthentication::class, $beta);
        $alphaProjectId = (string) $this->pdo
            ->query("SELECT id FROM pa_example_project WHERE code = 'alpha-project'")
            ->fetchColumn();
        $resolver = new PdoTargetResolver($this->pdo);
        self::assertSame(
            $alphaProjectId,
            $resolver->resolveAndValidate(
                $alpha->context,
                new TypedResourceTargetSet('example.project', [$alphaProjectId]),
            )->targets->sets[0]->targetIds[0],
        );

        try {
            $resolver->resolveAndValidate(
                $beta->context,
                new TypedResourceTargetSet('example.project', [$alphaProjectId]),
            );
        } catch (ModuleException $exception) {
            self::assertSame('AUTHZ_TARGET_NOT_FOUND', $exception->errorCode);

            return;
        }

        self::fail('Restored Beta context must not resolve Alpha target.');
    }

    private function login(?string $tenantCode): TenantSelectionRequired|TenantAuthentication
    {
        $password = getenv('RECOVERY_FIXTURE_PASSWORD');
        self::assertIsString($password);
        self::assertNotSame('', $password);

        return $this->auth->login(
            'owner@example.test',
            $password,
            $tenantCode,
            '127.0.0.1',
            'Recovery Test',
            'recovery-login-' . ($tenantCode ?? 'select') . '-' . bin2hex(random_bytes(4)),
        );
    }

    private function scalar(string $sql): int
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }
}
