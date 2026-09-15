<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Security;

use PHPUnit\Framework\TestCase;

final class SettingsSecurityTest extends TestCase
{
    public function testServiceUsesNativeTransactionsAndModelsWithoutParallelPersistence(): void
    {
        $root = dirname(__DIR__, 3);
        $service = (string) file_get_contents($root . '/backend/app/setting/SettingsHttpService.php');
        $admin = (string) file_get_contents($root . '/packages/php/settings/src/Application/SettingAdminService.php');
        $tenant = (string) file_get_contents($root . '/backend/app/controller/api/v1/SettingsController.php');
        $platform = (string) file_get_contents(
            $root . '/backend/app/controller/api/platform/v1/PlatformSettingsController.php',
        );

        self::assertStringContainsString('Db::transaction(', $service);
        self::assertStringContainsString('TenantSettingValue', $admin);
        self::assertStringNotContainsString('AtomicOperationAdapter', $service . $admin);
        self::assertStringNotContainsString('SettingStore', $service . $admin);
        self::assertStringNotContainsString('PDO', $service . $admin);
        self::assertStringNotContainsString('IdempotencyMiddleware', $service);
        self::assertStringNotContainsString('PDO', $tenant);
        self::assertStringNotContainsString('PDO', $platform);
        self::assertStringNotContainsString('pa_setting_', $tenant . $platform);
    }

    public function testAuditMetadataBuilderCannotAcceptValuesOrSecretMaterial(): void
    {
        $factory = (string) file_get_contents(
            dirname(__DIR__, 3) . '/backend/app/setting/SettingsHttpService.php',
        );
        self::assertMatchesRegularExpression(
            '/private static function auditMetadata\(.*?return \[\s*'
            . "'module_key'.*?'setting_key'.*?'scope'.*?'changed_fields'.*?'revision'.*?\];/s",
            $factory,
        );
        self::assertStringNotContainsString("'value' => \$definition", $factory);
        self::assertStringNotContainsString("'value' => \$setting", $factory);
    }
}
