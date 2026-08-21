<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use PDO;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\controller\api\v1\AccountController;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use think\App;
use think\Request;
use think\Response;

final class AccountSelfServiceHttpIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_account_http_test';
    private const EMAIL = 'account-owner@example.test';
    private const PASSWORD = 'Account-HTTP-P1-2026!';

    private PDO $admin;
    private PDO $pdo;
    private string $accessToken;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through scripts/test-integration.');
        }
        $port = (int) (getenv('MYSQL_PORT') ?: 3306);
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'AUTH_IDENTIFIER_HMAC_KEY'] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv("DB_PORT={$port}");
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv("DB_PASSWORD={$rootPassword}");
        putenv('AUTH_IDENTIFIER_HMAC_KEY=account-http-integration-hmac-key-2026');

        $root = dirname(__DIR__, 3);
        (new InstallWorkflow($root, $this->pdo))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            self::EMAIL,
            self::PASSWORD,
            'Account Owner',
            [
                'code' => 'account-http',
                'name' => 'Account HTTP',
                'owner_email' => self::EMAIL,
                'owner_name' => 'Account Owner',
            ],
        );
        $authentication = TenantAuthRuntimeFactory::create(pdo: $this->pdo)->login(
            self::EMAIL,
            self::PASSWORD,
            'account-http',
            '127.0.0.1',
            'Account HTTP integration',
            'req_account_http_login',
        );
        self::assertInstanceOf(TenantAuthentication::class, $authentication);
        $this->accessToken = $authentication->tokens->access->expose();
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
        foreach ($this->originalEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
    }

    public function testAccountProfileAndPasswordRunThroughTheRealHttpChain(): void
    {
        $profile = $this->request('GET', '/api/v1/account');
        self::assertSame(200, $profile->getCode());
        self::assertSame('Account Owner', $profile->getData()['data']['display_name']);
        self::assertSame('a***@example.test', $profile->getData()['data']['credential']['identifier_masked']);
        self::assertArrayNotHasKey('secret_hash', $profile->getData()['data']['credential']);

        $updated = $this->request('PATCH', '/api/v1/account', [
            'display_name' => 'Updated HTTP Owner',
            'avatar_uri' => 'https://cdn.example.test/account-owner.png',
        ]);
        self::assertSame(200, $updated->getCode());
        self::assertSame('Updated HTTP Owner', $updated->getData()['data']['display_name']);

        $denied = $this->request('POST', '/api/v1/account/password', [
            'current_password' => 'wrong-current-password',
            'new_password' => 'Replacement-HTTP-P1-2026!',
        ]);
        self::assertSame(422, $denied->getCode());
        self::assertSame('CURRENT_PASSWORD_INVALID', $denied->getData()['code']);

        $changed = $this->request('POST', '/api/v1/account/password', [
            'current_password' => self::PASSWORD,
            'new_password' => 'Replacement-HTTP-P1-2026!',
        ]);
        self::assertSame(204, $changed->getCode());
        self::assertStringContainsString('Max-Age=0', $changed->getHeader('Set-Cookie'));

        $revoked = $this->request('GET', '/api/v1/account');
        self::assertSame(401, $revoked->getCode());
        self::assertSame('AUTH_TOKEN_INVALID', $revoked->getData()['code']);
        self::assertSame(2, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_auth_security_event
WHERE event_type IN ('password_change_denied', 'password_changed')
SQL));
        self::assertSame(2, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_audit_event
WHERE action IN ('account.profile.changed', 'account.password.changed')
SQL));
    }

    public function testPasswordChangeRateLimitReturnsRetryAfter(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $denied = $this->request('POST', '/api/v1/account/password', [
                'current_password' => 'wrong-current-password',
                'new_password' => 'Replacement-HTTP-P1-2026!',
            ]);
            self::assertSame(422, $denied->getCode());
            self::assertSame('CURRENT_PASSWORD_INVALID', $denied->getData()['code']);
        }

        $rateLimited = $this->request('POST', '/api/v1/account/password', [
            'current_password' => 'wrong-current-password',
            'new_password' => 'Replacement-HTTP-P1-2026!',
        ]);

        self::assertSame(429, $rateLimited->getCode());
        self::assertSame('PASSWORD_CHANGE_RATE_LIMITED', $rateLimited->getData()['code']);
        self::assertSame('900', $rateLimited->getHeader('Retry-After'));
        self::assertSame(1, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_auth_security_event
WHERE event_type = 'password_change_rate_limited'
SQL));
    }

    public function testProfileUpdateRejectsMissingAndUndeclaredFields(): void
    {
        $invalidBodies = [
            'empty body' => [[], 'ACCOUNT_PROFILE_INVALID'],
            'missing display name' => [['avatar_uri' => null], 'ACCOUNT_PROFILE_INVALID'],
            'missing avatar URI' => [['display_name' => 'Boundary Owner'], 'AVATAR_URI_INVALID'],
            'account identifier' => [[
                'display_name' => 'Boundary Owner',
                'avatar_uri' => null,
                'account_id' => '999999',
            ], 'ACCOUNT_PROFILE_INVALID'],
            'tenant identifier' => [[
                'display_name' => 'Boundary Owner',
                'avatar_uri' => null,
                'tenant_id' => '999999',
            ], 'ACCOUNT_PROFILE_INVALID'],
            'unknown field' => [[
                'display_name' => 'Boundary Owner',
                'avatar_uri' => null,
                'unexpected' => true,
            ], 'ACCOUNT_PROFILE_INVALID'],
        ];

        foreach ($invalidBodies as $description => [$body, $expectedCode]) {
            $response = $this->request('PATCH', '/api/v1/account', $body);

            self::assertSame(422, $response->getCode(), $description);
            self::assertSame('application/problem+json', $response->getHeader('Content-Type'), $description);
            self::assertSame($expectedCode, $response->getData()['code'], $description);
        }

        self::assertSame(0, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_audit_event
WHERE action = 'account.profile.changed'
SQL));
    }

    public function testPasswordChangeRejectsMissingAndUndeclaredFields(): void
    {
        $validBody = [
            'current_password' => self::PASSWORD,
            'new_password' => 'Replacement-HTTP-P1-2026!',
        ];
        $invalidBodies = [
            'empty body' => [[], 'CURRENT_PASSWORD_INVALID'],
            'missing current password' => [[
                'new_password' => $validBody['new_password'],
            ], 'CURRENT_PASSWORD_INVALID'],
            'missing new password' => [[
                'current_password' => $validBody['current_password'],
            ], 'NEW_PASSWORD_INVALID'],
            'account identifier' => [[...$validBody, 'account_id' => '999999'], 'CURRENT_PASSWORD_INVALID'],
            'tenant identifier' => [[...$validBody, 'tenant_id' => '999999'], 'CURRENT_PASSWORD_INVALID'],
            'unknown field' => [[...$validBody, 'unexpected' => true], 'CURRENT_PASSWORD_INVALID'],
        ];

        foreach ($invalidBodies as $description => [$body, $expectedCode]) {
            $response = $this->request('POST', '/api/v1/account/password', $body);

            self::assertSame(422, $response->getCode(), $description);
            self::assertSame('application/problem+json', $response->getHeader('Content-Type'), $description);
            self::assertSame($expectedCode, $response->getData()['code'], $description);
            self::assertNull($response->getHeader('Retry-After'), $description);
        }

        self::assertSame(0, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_auth_security_event
WHERE event_type IN ('password_change_denied', 'password_change_rate_limited', 'password_changed')
SQL));
    }

    public function testPasswordChangePreparesCookieMetadataBeforeThePasswordMutation(): void
    {
        $method = new ReflectionMethod(AccountController::class, 'changePassword');
        $fileName = $method->getFileName();
        self::assertIsString($fileName);
        $sourceLines = file($fileName);
        self::assertIsArray($sourceLines);
        $source = implode('', array_slice(
            $sourceLines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $cookieMetadataPosition = strpos($source, 'TenantRefreshCookie::clear');
        $passwordMutationPosition = strpos($source, '->changePassword(');
        self::assertIsInt($cookieMetadataPosition);
        self::assertIsInt($passwordMutationPosition);
        self::assertLessThan(
            $passwordMutationPosition,
            $cookieMetadataPosition,
            'Cookie metadata must be prepared before the transactional password mutation starts.',
        );
        self::assertStringNotContainsString(
            'TenantAuthRuntimeFactory::',
            $source,
            'Password changes must not construct the database and password-hashing authentication Runtime.',
        );
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $url, ?array $body = null): Response
    {
        $outputLevel = ob_get_level();
        $request = (new Request())
            ->setMethod($method)
            ->setUrl($url)
            ->withServer([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $url,
                'HTTP_HOST' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->withHeader([
                'accept' => 'application/json',
                'authorization' => 'Bearer ' . $this->accessToken,
                'content-type' => 'application/json',
                'user-agent' => 'Peanut account HTTP test',
                'x-request-id' => 'req_account_' . bin2hex(random_bytes(8)),
            ]);
        if ($body !== null) {
            $request->withPost($body)->withInput(json_encode($body, JSON_THROW_ON_ERROR));
        }
        $app = new App(dirname(__DIR__, 2));
        $http = $app->http;

        try {
            $response = $http->run($request);
            $http->end($response);

            return $response;
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            restore_error_handler();
            restore_exception_handler();
        }
    }

    private function scalarCount(string $sql): int
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Account HTTP count query failed.');
        }

        return (int) $statement->fetchColumn();
    }
}
