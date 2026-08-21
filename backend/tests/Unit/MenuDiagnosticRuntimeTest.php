<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Unit;

use PeanutAdmin\App\controller\api\MenuDiagnosticRuntime;
use PeanutAdmin\Kernel\Menu\MenuDefinition;
use PHPUnit\Framework\TestCase;

final class MenuDiagnosticRuntimeTest extends TestCase
{
    public function testExplainsPermissionAndParentVisibilityWithoutChangingNavigation(): void
    {
        $items = MenuDiagnosticRuntime::explain([
            new MenuDefinition('core.group', 'core', 'tenant', null, 'group', 'Group', null, null, null, null, ['admin-web']),
            new MenuDefinition('core.page', 'core', 'tenant', 'core.group', 'page', 'Page', 'tenant.page', '/app/page', 'core.page', 'core.page.read', ['admin-web']),
        ], 'tenant', 'admin-web', static fn(): bool => true, static fn(): bool => true, static fn(): bool => false);

        self::assertSame('empty_group', $items[0]['reason']);
        self::assertFalse($items[0]['visible']);
        self::assertSame('permission_not_granted', $items[1]['reason']);
        self::assertFalse($items[1]['visible']);
        self::assertSame('/app/page', $items[1]['trusted_route_path']);
    }
}
