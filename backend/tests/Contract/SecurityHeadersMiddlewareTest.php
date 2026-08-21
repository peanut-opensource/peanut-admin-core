<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use PeanutAdmin\App\middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use think\Request;
use think\Response;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testEveryApiResponseReceivesTheRestrictiveHeaderSet(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/security.php';
        self::assertSame([], $config['cors']['allowed_origins']);
        self::assertFalse($config['cors']['allow_credentials']);
        $middleware = new SecurityHeadersMiddleware($config['headers']);

        $response = $middleware->handle(
            new Request(),
            static fn(): Response => Response::create(['status' => 'ok'], 'json'),
        );

        self::assertSame("default-src 'none'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'", $response->getHeader('Content-Security-Policy'));
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
        self::assertSame('same-origin', $response->getHeader('Cross-Origin-Resource-Policy'));
        self::assertSame('no-store', $response->getHeader('Cache-Control'));
    }

    public function testMissingHeaderConfigurationFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SECURITY_HEADERS_UNAVAILABLE');

        (new SecurityHeadersMiddleware([]))->handle(
            new Request(),
            static fn(): Response => Response::create([], 'json'),
        );
    }
}
