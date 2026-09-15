<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use DateTimeImmutable;
use PeanutAdmin\App\middleware\ModuleGuard;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tests\Integration\Schema\DatabaseTestCase;
use think\Request;
use think\Response;

require_once dirname(__DIR__, 3) . '/packages/php/kernel/tests/Integration/Schema/DatabaseTestCase.php';

final class ModuleGuardMiddlewareTest extends DatabaseTestCase
{
    private const NOW = '2026-07-16 12:00:00.000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner->migrate();
        $this->insert('pa_tenant', [
            'id' => 9,
            'code' => 'module-guard',
            'name' => 'Module Guard',
            'display_name' => 'Module Guard',
            'status' => 'active',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
        $this->insert('pa_module_installation', [
            'module_key' => 'example.work-item',
            'installed_version' => '1.0.0',
            'manifest_schema_version' => 1,
            'manifest_digest' => str_repeat('a', 64),
            'status' => 'active',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    public function testEnabledModuleContinuesWithTrustedTenantContext(): void
    {
        $this->insert('pa_tenant_module', [
            'tenant_id' => 9,
            'module_key' => 'example.work-item',
            'status' => 'enabled',
            'source' => 'manual',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
        $middleware = new ModuleGuard(new ModuleAvailabilityService());
        $request = (new Request())->withRoute(['tenant_context' => $this->context()]);

        $response = $middleware->handle(
            $request,
            static fn(): Response => Response::create(['ok' => true], 'json', 200),
            'example.work-item',
        );

        self::assertSame(200, $response->getCode());
    }

    public function testDisabledModuleStopsBeforeTheController(): void
    {
        $middleware = new ModuleGuard(new ModuleAvailabilityService());
        $request = (new Request())->withRoute(['tenant_context' => $this->context()]);

        try {
            $middleware->handle(
                $request,
                static function (): never {
                    self::fail('Controller must not run for a disabled module.');
                },
                'example.work-item',
            );
        } catch (ModuleException $exception) {
            self::assertSame('MODULE_TENANT_DISABLED', $exception->errorCode);

            return;
        }

        self::fail('Expected module guard to reject the request.');
    }

    private function context(): TenantContext
    {
        return TenantContext::fromValidatedSession(new ValidatedTenantSession(
            1,
            'session',
            9,
            10,
            11,
            'web',
            new DateTimeImmutable('2026-07-16T12:00:00Z'),
            1,
        ), 'req_module_guard');
    }

}
