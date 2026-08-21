<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Smoke;

use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use PeanutAdmin\Kernel\Package as KernelPackage;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class HostPackageBoundaryTest extends TestCase
{
    public function testReferenceHostConsumesPublicPackageExports(): void
    {
        self::assertSame('peanut-admin/core', KernelPackage::NAME);
        self::assertSame('peanut-admin/core', DataPermissionPackage::NAME);
    }
}
