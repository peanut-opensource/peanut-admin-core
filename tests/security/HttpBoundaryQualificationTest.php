<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HttpBoundaryQualificationTest extends TestCase
{
    public function testBrowserAndHttpBoundaryDefaultsAreRestrictive(): void
    {
        $root = dirname(__DIR__, 2);
        $securityConfig = $root . '/backend/config/security.php';
        $globalMiddleware = $root . '/backend/app/middleware.php';
        $provider = $root . '/backend/app/provider.php';
        self::assertFileExists($securityConfig);
        self::assertFileExists($globalMiddleware);
        self::assertFileExists($provider);

        $config = require $securityConfig;
        self::assertSame([], $config['cors']['allowed_origins'] ?? null);
        self::assertFalse($config['cors']['allow_credentials'] ?? true);
        self::assertSame("default-src 'none'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'", $config['headers']['Content-Security-Policy'] ?? null);
        self::assertSame('nosniff', $config['headers']['X-Content-Type-Options'] ?? null);
        self::assertSame('no-referrer', $config['headers']['Referrer-Policy'] ?? null);

        $middleware = (string) file_get_contents($globalMiddleware);
        self::assertStringContainsString('SecurityHeadersMiddleware::class', $middleware);
        self::assertStringContainsString('ApiExceptionHandler::class', (string) file_get_contents($provider));
        $tenantCookie = (string) file_get_contents($root . '/packages/php/kernel/src/Http/TenantRefreshCookie.php');
        $tenantClient = (string) file_get_contents($root . '/packages/php/kernel/src/Auth/TenantClient.php');
        $platformCookie = (string) file_get_contents($root . '/packages/php/kernel/src/Auth/PlatformRefreshCookie.php');
        self::assertStringContainsString('__Host-', $tenantClient);
        self::assertStringContainsString('__Host-', $platformCookie);
        foreach (['Secure', 'HttpOnly', 'SameSite=Lax'] as $required) {
            self::assertStringContainsString($required, $tenantCookie);
            self::assertStringContainsString($required, $platformCookie);
        }
        self::assertNotSame($tenantCookie, $platformCookie);

        $source = '';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/backend')) as $file) {
            if ($file->isFile()) {
                $source .= (string) file_get_contents($file->getPathname());
            }
        }
        self::assertDoesNotMatchRegularExpression('/Access-Control-Allow-Origin[^\n]*\*/i', $source);
    }
}
