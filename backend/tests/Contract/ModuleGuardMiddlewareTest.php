<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\App\middleware\ModuleGuard;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleInstallationRecord;
use PeanutAdmin\Kernel\Module\ModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleRecord;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

final class ModuleGuardMiddlewareTest extends TestCase
{
    public function testEnabledModuleContinuesWithTrustedTenantContext(): void
    {
        $middleware = new ModuleGuard($this->createStub(PDO::class), $this->repository(
            new ModuleInstallationRecord('example.work-item', '1.0.0', 'active', 1, 'digest'),
            new TenantModuleRecord(9, 'example.work-item', 'enabled', null, null, 1),
        ));
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
        $middleware = new ModuleGuard($this->createStub(PDO::class), $this->repository(
            new ModuleInstallationRecord('example.work-item', '1.0.0', 'active', 1, 'digest'),
            null,
        ));
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

    private function repository(
        ?ModuleInstallationRecord $installation,
        ?TenantModuleRecord $tenantModule,
    ): ModuleRuntimeRepository {
        return new class ($installation, $tenantModule) implements ModuleRuntimeRepository {
            public function __construct(
                private readonly ?ModuleInstallationRecord $installation,
                private readonly ?TenantModuleRecord $tenantModule,
            ) {}

            public function installation(string $moduleKey): ?ModuleInstallationRecord
            {
                return $this->installation;
            }

            public function tenantModule(int $tenantId, string $moduleKey): ?TenantModuleRecord
            {
                return $this->tenantModule;
            }

            public function enabledDependents(int $tenantId, string $moduleKey): array
            {
                return [];
            }
        };
    }
}
