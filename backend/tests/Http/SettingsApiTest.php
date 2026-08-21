<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Http;

use DateTimeImmutable;
use PeanutAdmin\App\controller\api\platform\v1\PlatformSettingsController;
use PeanutAdmin\App\controller\api\v1\SettingsController;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\App\Modules\Peanut\Settings\ModuleProvider;
use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Settings\Application\EffectiveSetting;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SettingsApiTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     class-string,
     *     string,
     *     string,
     *     string,
     *     string,
     *     string,
     *     bool
     * }>
     */
    public static function operations(): iterable
    {
        yield 'deployment list' => [
            PlatformSettingsController::class,
            'listDeploymentSettings',
            'GET',
            '/api/platform/v1/settings',
            'platform',
            'platform.settings.read',
            false,
        ];
        yield 'deployment replace' => [
            PlatformSettingsController::class,
            'replaceDeploymentSetting',
            'PUT',
            '/api/platform/v1/settings/{module_key}/{setting_key}',
            'platform',
            'platform.settings.manage',
            true,
        ];
        yield 'deployment unset' => [
            PlatformSettingsController::class,
            'unsetDeploymentSetting',
            'DELETE',
            '/api/platform/v1/settings/{module_key}/{setting_key}',
            'platform',
            'platform.settings.manage',
            true,
        ];
        yield 'Tenant list' => [
            SettingsController::class,
            'listTenantSettings',
            'GET',
            '/api/v1/settings',
            'tenant',
            'peanut.settings.read',
            false,
        ];
        yield 'Tenant replace' => [
            SettingsController::class,
            'replaceTenantSetting',
            'PUT',
            '/api/v1/settings/{module_key}/{setting_key}',
            'tenant',
            'peanut.settings.manage',
            true,
        ];
        yield 'Tenant unset' => [
            SettingsController::class,
            'unsetTenantSetting',
            'DELETE',
            '/api/v1/settings/{module_key}/{setting_key}',
            'tenant',
            'peanut.settings.manage',
            true,
        ];
    }

    /** @param class-string $controller */
    #[DataProvider('operations')]
    public function testSixOperationsUseTheExactR02Contract(
        string $controller,
        string $method,
        string $httpMethod,
        string $path,
        string $audience,
        string $permission,
        bool $command,
    ): void {
        $operations = SettingsRuntimeFactory::operations();
        self::assertCount(6, $operations);
        self::assertArrayHasKey($method, $operations);

        $operation = $operations[$method];
        self::assertInstanceOf(ExternalOperationDefinition::class, $operation);
        self::assertSame($method, $operation->operationId);
        self::assertSame($httpMethod, $operation->method);
        self::assertSame($path, $operation->path);
        self::assertSame($audience, $operation->audience);
        self::assertSame('peanut.settings', $operation->moduleKey);
        self::assertSame([$permission], $operation->permission->permissionKeys);
        self::assertSame($command, $operation->atomicCommand);
        self::assertSame($command, $operation->idempotencyRequired);

        $handler = new ReflectionMethod($controller, $method);
        self::assertSame('think\\Response', (string) $handler->getReturnType());
        $attributes = $handler->getAttributes(OpenApiHandlerContract::class);
        self::assertCount(1, $attributes);
        self::assertSame(
            OpenApiHandlerContract::VERSIONED_HEADERS,
            $attributes[0]->newInstance()->headers,
        );
    }

    public function testReplaceInputIsStrictAndDefaultsToTheComparisonTime(): void
    {
        $comparisonTime = new DateTimeImmutable('2026-07-19T08:09:10.123+08:00');
        $input = SettingsRuntimeFactory::replaceInput(['value' => 'compact'], $comparisonTime);

        self::assertSame('compact', $input['value']);
        self::assertSame('2026-07-19T00:09:10.123+00:00', $input['effectiveAt']->format('Y-m-d\TH:i:s.vP'));
        self::assertNull($input['expiresAt']);
        self::assertSame('value', $input['changedFields']);

        foreach ([
            [],
            ['value' => 'compact', 'tenant_id' => 99],
            ['value' => 'compact', 'effective_at' => []],
            ['value' => 'compact', 'expires_at' => 'not-a-date'],
        ] as $body) {
            try {
                SettingsRuntimeFactory::replaceInput($body, $comparisonTime);
                self::fail('Expected invalid settings body to fail.');
            } catch (SettingException $exception) {
                self::assertSame(422, $exception->httpStatus);
                self::assertStringStartsWith('SETTING_', $exception->errorCode);
            }
        }
    }

    public function testReplaceInputRejectsInvalidCalendarDatesAndNonIncreasingIntervals(): void
    {
        foreach ([
            [
                'value' => 'compact',
                'effective_at' => '2026-02-30T00:00:00Z',
            ],
            [
                'value' => 'compact',
                'effective_at' => '2026-07-19T00:00:00.123456+08:00',
                'expires_at' => '2026-07-19T00:00:00.123456+08:00',
            ],
            [
                'value' => 'compact',
                'effective_at' => '2026-07-19T00:00:00.123456+08:00',
                'expires_at' => '2026-07-18T23:59:59.999999+08:00',
            ],
            [
                'value' => 'compact',
                'effective_at' => '2026-07-19T00:00:00.123100+08:00',
                'expires_at' => '2026-07-19T00:00:00.123200+08:00',
            ],
        ] as $body) {
            try {
                SettingsRuntimeFactory::replaceInput(
                    $body,
                    new DateTimeImmutable('2026-07-19T00:00:00Z'),
                );
                self::fail('Expected invalid effective interval to fail.');
            } catch (SettingException $exception) {
                self::assertSame(422, $exception->httpStatus);
                self::assertSame('SETTING_INTERVAL_INVALID', $exception->errorCode);
            }
        }

        $valid = SettingsRuntimeFactory::replaceInput([
            'value' => 'compact',
            'effective_at' => '2026-07-19T08:09:10.1+08:00',
            'expires_at' => '2026-07-19T08:09:10.123000+08:00',
        ], new DateTimeImmutable('2026-07-19T00:00:00Z'));
        self::assertSame('2026-07-19T00:09:10.100000+00:00', $valid['effectiveAt']->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-07-19T00:09:10.123000+00:00', $valid['expiresAt']?->format('Y-m-d\TH:i:s.uP'));
    }

    public function testResponseShapeMatchesTheWebParserAndRedactsSecrets(): void
    {
        $public = SettingsRuntimeFactory::item(
            $this->definition(false),
            new EffectiveSetting(
                'example.target',
                'display-density',
                'compact',
                'tenant',
                true,
                7,
                '"rev-7"',
                '2026-07-19T00:00:00.000Z',
                null,
                false,
            ),
        );
        self::assertSame([
            'module_key',
            'setting_key',
            'name',
            'description',
            'schema',
            'required',
            'secret',
            'configured',
            'source_scope',
            'value',
            'effective_at',
            'expires_at',
            'revision',
            'etag',
        ], array_keys($public));
        self::assertSame('7', $public['revision']);
        self::assertSame('tenant', $public['source_scope']);
        self::assertSame('compact', $public['value']);

        $secret = SettingsRuntimeFactory::item(
            $this->definition(true),
            new EffectiveSetting(
                'example.target',
                'display-density',
                null,
                'tenant',
                true,
                8,
                '"rev-8"',
                '2026-07-19T00:00:00.000Z',
                null,
                true,
            ),
        );
        self::assertArrayNotHasKey('value', $secret);
        self::assertTrue($secret['configured']);
        self::assertSame('"rev-8"', $secret['etag']);
    }

    public function testCollectionEtagIsStrongAndOrderSensitive(): void
    {
        $first = [['module_key' => 'a.module', 'setting_key' => 'first', 'etag' => '"rev-1"']];
        $second = [['module_key' => 'b.module', 'setting_key' => 'second', 'etag' => '"rev-1"']];

        self::assertMatchesRegularExpression('/^"settings-[a-f0-9]{64}"$/', SettingsRuntimeFactory::collectionEtag($first));
        self::assertNotSame(
            SettingsRuntimeFactory::collectionEtag([...$first, ...$second]),
            SettingsRuntimeFactory::collectionEtag([...$second, ...$first]),
        );
    }

    public function testTenantMenuResolvesTheRegisteredSettingsContributionRoute(): void
    {
        $menu = RuntimeModuleRegistry::compile()->menus['peanut.settings.page'] ?? null;

        self::assertIsArray($menu);
        self::assertSame('peanut.settings.list', $menu['route_name'] ?? null);
        self::assertSame('peanut.settings.page', $menu['component_key'] ?? null);
        self::assertSame('peanut.settings.read', $menu['required_permission'] ?? null);
    }

    public function testModuleProviderExposesTheSettingsModuleIdentity(): void
    {
        $provider = new ModuleProvider();

        self::assertSame('peanut.settings', $provider->moduleKey());
    }

    public function testSettingsModuleDeclaresOnlyFunctionalPermissionsAndNoParallelDataResource(): void
    {
        $root = dirname(__DIR__, 3) . '/backend/app/Modules/Peanut/Settings/Resources';
        $permissions = json_decode(
            (string) file_get_contents($root . '/permissions.json'),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame([
            'platform.settings.read',
            'platform.settings.manage',
            'peanut.settings.read',
            'peanut.settings.manage',
        ], array_column($permissions, 'key'));
        self::assertSame([], json_decode(
            (string) file_get_contents($root . '/protected-resources.json'),
            true,
            32,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame([], json_decode(
            (string) file_get_contents($root . '/setting-definitions.json'),
            true,
            32,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testHostConfigurationAcceptsBothTrustedAudiencesAndUsesCanonicalArtifacts(): void
    {
        $configuration = SettingsRuntimeFactory::hostConfiguration();

        self::assertSame(['admin-web', 'platform-web'], $configuration->clientKeys);
        self::assertSame(
            'packages/web/admin-core/src/generated/api.d.ts',
            $configuration->generatedTypeArtifact,
        );
        self::assertSame('backend/route/openapi-generated.php', $configuration->generatedRouteArtifact);
        self::assertSame('docs/api/openapi.yaml', $configuration->openApiDocument);
    }

    private function definition(bool $secret): SettingDefinition
    {
        return new SettingDefinition(
            'example.target',
            'display-density',
            'Display density',
            'Controls the fictional display density.',
            $secret
                ? ['$schema' => 'https://json-schema.org/draft/2020-12/schema', 'type' => 'string', 'minLength' => 1, 'maxLength' => 256]
                : ['$schema' => 'https://json-schema.org/draft/2020-12/schema', 'type' => 'string'],
            false,
            $secret,
            ['tenant'],
            null,
            null,
            false,
            null,
            str_repeat('a', 64),
        );
    }
}
