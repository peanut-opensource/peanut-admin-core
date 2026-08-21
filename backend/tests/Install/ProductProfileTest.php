<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Install;

use PeanutAdmin\App\command\InstallProductProfile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductProfileTest extends TestCase
{
    public function testReferenceProfileIsStaticAndNonAuthoritative(): void
    {
        $root = dirname(__DIR__, 3);
        $profile = InstallProductProfile::load(
            $root . '/profiles/reference-admin.json',
            $root . '/schemas/product-profile.schema.json',
        );

        self::assertSame('reference-admin', $profile->key);
        self::assertSame([
            'peanut.settings',
            'peanut.reference-codes',
            'peanut.file-media',
            'peanut.task-job',
            'peanut.notification-sms',
            'peanut.import-export',
            'peanut.integration-security',
            'example.target',
            'example.reference',
            'example.work-item',
        ], $profile->moduleKeys());
        self::assertSame([], $profile->moduleConfig('example.target'));
        self::assertSame(
            ['code' => 'root', 'name' => 'Organization'],
            $profile->defaultDepartment,
        );
        self::assertSame(['tenant-owner', 'tenant-operator'], $profile->roleTemplates);
    }

    public function testProfileRejectsTenantAndPermissionAssignments(): void
    {
        $root = dirname(__DIR__, 3);
        $path = tempnam(sys_get_temp_dir(), 'peanut-profile-');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            'schema_version' => 1,
            'key' => 'unsafe-profile',
            'name' => 'Unsafe Profile',
            'modules' => [],
            'tenant_id' => 9,
            'permission_grants' => ['all'],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->expectException(RuntimeException::class);
            InstallProductProfile::load($path, $root . '/schemas/product-profile.schema.json');
        } finally {
            unlink($path);
        }
    }
}
