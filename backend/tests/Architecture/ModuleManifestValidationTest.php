<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Architecture;

use PeanutAdmin\App\module\OpisManifestSchemaValidator;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleException;
use PHPUnit\Framework\TestCase;

final class ModuleManifestValidationTest extends TestCase
{
    public function testOpisValidatorAcceptsTheMinimalP0ManifestAndCatalog(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );
        $validator->assertValid(json_decode((string) json_encode([
            'schema_version' => 1,
            'key' => 'example.target',
            'name' => 'Example Target',
            'description' => 'Fixture module',
            'version' => '1.0.0',
            'kernel_constraint' => '^1.0',
            'license' => 'Apache-2.0',
            'backend' => ['provider' => 'PeanutAdmin\\App\\Modules\\Example\\Target\\ModuleProvider'],
            'frontend' => (object) [],
            'database' => ['owned_tables' => []],
            'contracts' => ['exports' => [], 'events' => []],
            'tenant' => ['enableable' => true, 'requires' => []],
            'catalog' => [
                'menus' => [],
                'permissions' => [],
                'protected_resources' => [],
                'target_types' => [],
                'data_conditions' => [],
                'system_actors' => [],
            ],
        ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));

        self::expectNotToPerformAssertions();
    }

    public function testOpisValidatorAcceptsFrontendRoutesButRejectsBackendRoutes(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Example/WorkItem/module.json'),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $validator->assertValid($manifest);
        $manifest->backend->routes = 'Http/routes.php';

        $this->expectException(ModuleException::class);
        $validator->assertValid($manifest);
    }

    public function testOpisValidatorRejectsUnknownSchemaVersionAndProperties(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );

        $this->expectException(ModuleException::class);
        $validator->assertValid((object) [
            'schema_version' => 2,
            'unexpected' => true,
        ]);
    }

    public function testOpisValidatorAllowsHostOwnedBusinessTableNames(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );
        $manifest = [
            'schema_version' => 1,
            'key' => 'example.target',
            'name' => 'Example Target',
            'description' => 'Fixture module',
            'version' => '1.0.0',
            'kernel_constraint' => '^1.0',
            'license' => 'Apache-2.0',
            'backend' => ['provider' => 'Example\\App\\Modules\\Example\\Target\\ModuleProvider'],
            'frontend' => (object) [],
            'database' => ['owned_tables' => ['example_target']],
            'contracts' => ['exports' => [], 'events' => []],
            'tenant' => ['enableable' => true, 'requires' => []],
        ];

        $validator->assertValid(json_decode(
            (string) json_encode($manifest, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        ));
        $manifest['database']['owned_tables'] = ['unsafe-table'];

        $this->expectException(ModuleException::class);
        $validator->assertValid(json_decode(
            (string) json_encode($manifest, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testOpisValidatorAcceptsASettingsDefinitionResource(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );
        $validator->assertValid(json_decode((string) json_encode([
            'schema_version' => 1,
            'key' => 'example.target',
            'name' => 'Example Target',
            'description' => 'Fixture module',
            'version' => '1.0.0',
            'kernel_constraint' => '^1.0',
            'license' => 'Apache-2.0',
            'backend' => [
                'provider' => 'PeanutAdmin\\App\\Modules\\Example\\Target\\ModuleProvider',
                'setting_definitions' => 'Resources/setting-definitions.json',
            ],
            'frontend' => (object) [],
            'database' => ['owned_tables' => []],
            'contracts' => ['exports' => [], 'events' => []],
            'tenant' => ['enableable' => true, 'requires' => []],
        ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));

        self::expectNotToPerformAssertions();
    }

    public function testOpisValidatorAcceptsAReferenceCodeSetResource(): void
    {
        $validator = new OpisManifestSchemaValidator(
            dirname(__DIR__, 3) . '/packages/php/kernel/resources/schemas/module-manifest.schema.json',
        );
        $validator->assertValid(json_decode((string) json_encode([
            'schema_version' => 1,
            'key' => 'example.target',
            'name' => 'Example Target',
            'description' => 'Fixture module',
            'version' => '1.0.0',
            'kernel_constraint' => '^1.0',
            'license' => 'Apache-2.0',
            'backend' => [
                'provider' => 'PeanutAdmin\\App\\Modules\\Example\\Target\\ModuleProvider',
                'reference_code_sets' => 'Resources/reference-code-sets.json',
            ],
            'frontend' => (object) [],
            'database' => ['owned_tables' => []],
            'contracts' => ['exports' => [], 'events' => []],
            'tenant' => ['enableable' => true, 'requires' => []],
        ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));

        self::expectNotToPerformAssertions();
    }

    public function testReferenceHostRegistersTheAdministrationModules(): void
    {
        $moduleKeys = RuntimeModuleRegistry::compile()->moduleKeys();

        self::assertContains('peanut.settings', $moduleKeys);
        self::assertContains('peanut.reference-codes', $moduleKeys);
    }
}
