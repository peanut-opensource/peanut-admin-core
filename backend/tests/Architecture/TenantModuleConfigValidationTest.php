<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Architecture;

use PeanutAdmin\App\module\OpisTenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PHPUnit\Framework\TestCase;

final class TenantModuleConfigValidationTest extends TestCase
{
    public function testModuleWithoutSchemaAcceptsOnlyEmptyConfig(): void
    {
        $validator = new OpisTenantModuleConfigValidator();
        $manifest = ManifestDocument::fromArray(__DIR__, [
            'key' => 'example.empty-config',
            'backend' => [],
        ]);

        $validator->assertValid($manifest, []);
        self::addToAssertionCount(1);

        try {
            $validator->assertValid($manifest, ['unexpected' => true]);
        } catch (ModuleException $exception) {
            self::assertSame('MODULE_CONFIG_INVALID', $exception->errorCode);

            return;
        }

        self::fail('A Module without config_schema must reject non-empty config.');
    }

    public function testDeclaredSchemaIsLoadedInsideTheModuleRoot(): void
    {
        $root = dirname(__DIR__) . '/fixtures/module-config';
        $manifest = ManifestDocument::fromArray($root, [
            'key' => 'example.configured',
            'backend' => ['config_schema' => 'tenant-config.schema.json'],
        ]);
        $validator = new OpisTenantModuleConfigValidator();

        $validator->assertValid($manifest, ['mode' => 'strict']);
        self::addToAssertionCount(1);

        $this->expectException(ModuleException::class);
        $validator->assertValid($manifest, ['mode' => 'unknown']);
    }
}
