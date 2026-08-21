<?php

declare(strict_types=1);

namespace PeanutAdmin\App\setting;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use LogicException;
use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\DataPermissionAdapter;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\PermissionRequirement;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Host\AtomicOperationAdapter;
use PeanutAdmin\Kernel\Host\AuthorizedExternalOperation;
use PeanutAdmin\Kernel\Host\ExternalHostConfiguration;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Kernel\Host\ExternalOperationHost;
use PeanutAdmin\Kernel\Host\ExternalOperationRequest;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use PeanutAdmin\Kernel\Host\ModuleAvailabilityAdapter;
use PeanutAdmin\Kernel\Host\PermissionAdapter;
use PeanutAdmin\Kernel\Host\ProblemDetailsAdapter;
use PeanutAdmin\Kernel\Host\TrustedContextAdapter;
use PeanutAdmin\Kernel\Host\TypedTargetAdapter;
use PeanutAdmin\Kernel\Http\PermissionMiddleware;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Settings\Application\EffectiveSetting;
use PeanutAdmin\Settings\Application\SettingAdminService;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Application\SettingResolver;
use PeanutAdmin\Settings\Cache\ArrayRevisionedSettingCache;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;
use PeanutAdmin\Settings\Secret\SodiumSecretProtector;
use think\Request;
use think\Response;

final class SettingsRuntimeFactory
{
    /** @return array<string, ExternalOperationDefinition> */
    public static function operations(): array
    {
        return [
            'listDeploymentSettings' => self::operation(
                'listDeploymentSettings',
                'GET',
                '/api/platform/v1/settings',
                'platform',
                'platform.settings.read',
            ),
            'replaceDeploymentSetting' => self::operation(
                'replaceDeploymentSetting',
                'PUT',
                '/api/platform/v1/settings/{module_key}/{setting_key}',
                'platform',
                'platform.settings.manage',
                true,
            ),
            'unsetDeploymentSetting' => self::operation(
                'unsetDeploymentSetting',
                'DELETE',
                '/api/platform/v1/settings/{module_key}/{setting_key}',
                'platform',
                'platform.settings.manage',
                true,
            ),
            'listTenantSettings' => self::operation(
                'listTenantSettings',
                'GET',
                '/api/v1/settings',
                'tenant',
                'peanut.settings.read',
            ),
            'replaceTenantSetting' => self::operation(
                'replaceTenantSetting',
                'PUT',
                '/api/v1/settings/{module_key}/{setting_key}',
                'tenant',
                'peanut.settings.manage',
                true,
            ),
            'unsetTenantSetting' => self::operation(
                'unsetTenantSetting',
                'DELETE',
                '/api/v1/settings/{module_key}/{setting_key}',
                'tenant',
                'peanut.settings.manage',
                true,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{
     *     value: mixed,
     *     effectiveAt: DateTimeImmutable,
     *     expiresAt: DateTimeImmutable|null,
     *     changedFields: non-empty-string
     * }
     */
    public static function replaceInput(array $body, DateTimeImmutable $comparisonTime): array
    {
        $unknown = array_diff(array_keys($body), ['value', 'effective_at', 'expires_at']);
        if ($unknown !== []) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting request contains unknown fields.');
        }
        if (!array_key_exists('value', $body)) {
            throw SettingException::invalid('SETTING_VALUE_INVALID', 'A replacement setting value is required.');
        }
        $effectiveAt = array_key_exists('effective_at', $body)
            ? self::date($body['effective_at'], 'effective_at', false)
            : $comparisonTime->setTimezone(new DateTimeZone('UTC'));
        $expiresAt = array_key_exists('expires_at', $body)
            ? self::date($body['expires_at'], 'expires_at', true)
            : null;
        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', 'effective_at is required.');
        }
        SettingAdminService::assertValidInterval($effectiveAt, $expiresAt);

        $changedFields = 'value';
        if (array_key_exists('effective_at', $body)) {
            $changedFields .= ',effective_at';
        }
        if (array_key_exists('expires_at', $body)) {
            $changedFields .= ',expires_at';
        }

        return [
            'value' => $body['value'],
            'effectiveAt' => $effectiveAt,
            'expiresAt' => $expiresAt,
            'changedFields' => $changedFields,
        ];
    }

    /** @return array<string, mixed> */
    public static function item(SettingDefinition $definition, EffectiveSetting $setting): array
    {
        $item = [
            'module_key' => $definition->moduleKey,
            'setting_key' => $definition->key,
            'name' => $definition->name,
            'description' => $definition->description,
            'schema' => $definition->schema,
            'required' => $definition->required,
            'secret' => $definition->secret,
            'configured' => $setting->configured,
            'source_scope' => $setting->source,
        ];
        if (!$definition->secret) {
            $item['value'] = $setting->value;
        }

        return [
            ...$item,
            'effective_at' => $setting->effectiveAt,
            'expires_at' => $setting->expiresAt,
            'revision' => (string) $setting->revision,
            'etag' => $setting->etag,
        ];
    }

    /** @param list<array<string, mixed>> $items */
    public static function collectionEtag(array $items): string
    {
        try {
            $encoded = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new ModuleException('MODULE_CONTRACT_INVALID', 'Settings response encoding failed.');
        }

        return '"settings-' . hash('sha256', $encoded) . '"';
    }

    public static function replaceTenant(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['replaceTenantSetting'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/v1/settings/' . rawurlencode($moduleKey) . '/' . rawurlencode($settingKey),
            'tenant_context',
        );
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $settingKey): ExternalOperationResult {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof TenantContext) {
                        throw SettingException::notFound('SETTING_TARGET_UNAUTHORIZED');
                    }
                    $body = self::requestPayload($command);
                    $input = self::replaceInput($body, $command->comparisonTime);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $settingKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $setting = self::admin($transaction)->replaceTenant(
                        $definition,
                        $context->tenantId,
                        $context->memberId,
                        $input['value'],
                        $input['effectiveAt'],
                        $input['expiresAt'],
                        self::precondition($command, 'if_match'),
                        self::precondition($command, 'if_none_match'),
                    );
                    $item = self::item($definition, $setting);

                    return new ExternalOperationResult(
                        200,
                        [
                            'data' => $item,
                            'meta' => ['request_id' => $command->requestId->value],
                        ],
                        'setting.tenant.replaced',
                        'peanut.settings.tenant.replace',
                        self::auditMetadata(
                            $definition,
                            'tenant',
                            $input['changedFields'],
                            (string) $setting->revision,
                        ),
                        'setting',
                        $definition->qualifiedKey(),
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $settingKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function listTenant(Request $request): Response
    {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['listTenantSettings'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/v1/settings',
            'tenant_context',
        );
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $query,
            ) use ($pdo, $modules): ExternalOperationResponse {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof TenantContext) {
                        throw SettingException::notFound('SETTING_TARGET_UNAUTHORIZED');
                    }
                    $items = [];
                    $resolver = self::resolver($pdo);
                    foreach (self::definitionRegistry($modules)->all() as $definition) {
                        if (!$definition->allows('tenant')
                            || !self::ownerAvailable($pdo, $definition, $context, $query->comparisonTime)) {
                            continue;
                        }
                        $items[] = self::item(
                            $definition,
                            $resolver->resolveTenant(
                                $definition,
                                $context->tenantId,
                                $query->comparisonTime,
                            ),
                        );
                    }

                    return new ExternalOperationResponse(
                        200,
                        [
                            'data' => ['items' => $items],
                            'meta' => ['request_id' => $query->requestId->value],
                        ],
                        headers: ['ETag' => self::collectionEtag($items)],
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function unsetTenant(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['unsetTenantSetting'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/v1/settings/' . rawurlencode($moduleKey) . '/' . rawurlencode($settingKey),
            'tenant_context',
        );
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $settingKey): ExternalOperationResult {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof TenantContext) {
                        throw SettingException::notFound('SETTING_TARGET_UNAUTHORIZED');
                    }
                    $input = self::unsetInput(self::requestPayload($command), $command->comparisonTime);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $settingKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $setting = self::admin($transaction)->unsetTenant(
                        $definition,
                        $context->tenantId,
                        $context->memberId,
                        $input['effectiveAt'],
                        self::requiredIfMatch($command),
                    );
                    $item = self::item($definition, $setting);

                    return new ExternalOperationResult(
                        200,
                        [
                            'data' => $item,
                            'meta' => ['request_id' => $command->requestId->value],
                        ],
                        'setting.tenant.unset',
                        'peanut.settings.tenant.unset',
                        self::auditMetadata(
                            $definition,
                            'tenant',
                            $input['changedFields'],
                            (string) $setting->revision,
                        ),
                        'setting',
                        $definition->qualifiedKey(),
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $settingKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function replaceDeployment(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['replaceDeploymentSetting'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/platform/v1/settings/' . rawurlencode($moduleKey) . '/' . rawurlencode($settingKey),
            'platform_context',
        );
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $settingKey): ExternalOperationResult {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof PlatformContext) {
                        throw SettingException::notFound('SETTING_ACTOR_UNAUTHORIZED');
                    }
                    $input = self::replaceInput(self::requestPayload($command), $command->comparisonTime);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $settingKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $setting = self::admin($transaction)->replaceDeployment(
                        $definition,
                        $input['value'],
                        $context->operatorId,
                        $input['effectiveAt'],
                        $input['expiresAt'],
                        self::precondition($command, 'if_match'),
                        self::precondition($command, 'if_none_match'),
                    );
                    $item = self::item($definition, $setting);

                    return new ExternalOperationResult(
                        200,
                        [
                            'data' => $item,
                            'meta' => ['request_id' => $command->requestId->value],
                        ],
                        'setting.deployment.replaced',
                        'peanut.settings.deployment.replace',
                        self::auditMetadata(
                            $definition,
                            'deployment',
                            $input['changedFields'],
                            (string) $setting->revision,
                        ),
                        'setting',
                        $definition->qualifiedKey(),
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $settingKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function unsetDeployment(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['unsetDeploymentSetting'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/platform/v1/settings/' . rawurlencode($moduleKey) . '/' . rawurlencode($settingKey),
            'platform_context',
        );
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $settingKey): ExternalOperationResult {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof PlatformContext) {
                        throw SettingException::notFound('SETTING_ACTOR_UNAUTHORIZED');
                    }
                    $input = self::unsetInput(self::requestPayload($command), $command->comparisonTime);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $settingKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $setting = self::admin($transaction)->unsetDeployment(
                        $definition,
                        $context->operatorId,
                        $input['effectiveAt'],
                        self::requiredIfMatch($command),
                    );
                    $item = self::item($definition, $setting);

                    return new ExternalOperationResult(
                        200,
                        [
                            'data' => $item,
                            'meta' => ['request_id' => $command->requestId->value],
                        ],
                        'setting.deployment.unset',
                        'peanut.settings.deployment.unset',
                        self::auditMetadata(
                            $definition,
                            'deployment',
                            $input['changedFields'],
                            (string) $setting->revision,
                        ),
                        'setting',
                        $definition->qualifiedKey(),
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $settingKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function listDeployment(Request $request): Response
    {
        $pdo = self::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = self::operations()['listDeploymentSettings'];
        $externalRequest = self::externalRequest(
            $request,
            $operation,
            '/api/platform/v1/settings',
            'platform_context',
        );
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $query,
            ) use ($pdo, $modules): ExternalOperationResponse {
                try {
                    $context = $authorized->context;
                    if (!$context instanceof PlatformContext) {
                        throw SettingException::notFound('SETTING_ACTOR_UNAUTHORIZED');
                    }
                    $items = [];
                    $resolver = self::resolver($pdo);
                    foreach (self::definitionRegistry($modules)->all() as $definition) {
                        if (!$definition->allows('deployment')
                            || !self::ownerAvailable($pdo, $definition, $context, $query->comparisonTime)) {
                            continue;
                        }
                        $items[] = self::item(
                            $definition,
                            $resolver->resolveDeployment($definition, $query->comparisonTime),
                        );
                    }

                    return new ExternalOperationResponse(
                        200,
                        [
                            'data' => ['items' => $items],
                            'meta' => ['request_id' => $query->requestId->value],
                        ],
                        headers: ['ETag' => self::collectionEtag($items)],
                    );
                } catch (SettingException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    /** @return array{inserted: int, updated: int, retired: int} */
    public static function synchronizeDefinitions(
        PDO $pdo,
        CompiledModuleRegistry $modules,
        DateTimeImmutable $now,
    ): array {
        return (new PdoSettingRepository($pdo))->synchronize(
            self::definitionRegistry($modules),
            $now,
        );
    }

    public static function definitionRegistry(CompiledModuleRegistry $modules): SettingDefinitionRegistry
    {
        $registry = new SettingDefinitionRegistry();
        $loader = new SettingDefinitionLoader();
        foreach ($modules->modules as $module) {
            $moduleKey = self::moduleKey($module);
            $relativePath = self::definitionPath($module);
            $definitions = $relativePath === null
                ? []
                : $loader->load(
                    $moduleKey,
                    self::ownedResourcePath($module, $relativePath),
                    self::targetDeclarations($module),
                );
            $registry->registerModule($moduleKey, $definitions);
        }

        return $registry;
    }

    public static function host(PDO $pdo, CompiledModuleRegistry $modules): ExternalOperationHost
    {
        $configuration = self::hostConfiguration();
        $permissions = new PermissionMiddleware(
            new TenantAuthorizationEvaluator(
                new PdoTenantAuthorizationRepository($pdo),
                new RevisionPermissionCache(),
            ),
            new PlatformAuthorizationEvaluator(
                new PdoPlatformAuthorizationRepository($pdo),
                new RevisionPermissionCache(),
            ),
        );
        $unusedDataAuthorization = new DataPermissionAdapter(
            static function (): never {
                throw new LogicException('Settings operations do not accept data-query authorization.');
            },
            static function (): never {
                throw new LogicException('Settings operations do not accept typed targets.');
            },
        );

        return new ExternalOperationHost(
            $configuration,
            new TrustedContextAdapter($configuration),
            new ModuleAvailabilityAdapter(
                $modules,
                new ModuleGuard(new PdoModuleRuntimeRepository($pdo)),
            ),
            new PermissionAdapter($permissions),
            new TypedTargetAdapter($unusedDataAuthorization),
            new AtomicOperationAdapter($pdo),
            new ProblemDetailsAdapter(),
        );
    }

    /** @return callable(AuthorizedExternalOperation, ExternalOperationRequest, PDO): void */
    private static function commandGuard(
        CompiledModuleRegistry $modules,
        string $moduleKey,
        string $settingKey,
    ): callable {
        return static function (
            AuthorizedExternalOperation $authorized,
            ExternalOperationRequest $request,
            PDO $transaction,
        ) use ($modules, $moduleKey, $settingKey): void {
            try {
                $definition = self::definitionRegistry($modules)->require($moduleKey, $settingKey);
                (new PdoSettingRepository($transaction))->assertCurrentDefinition($definition, true);
                self::assertModuleAvailable(
                    $transaction,
                    $authorized->operation->moduleKey,
                    $authorized->context,
                    $request->comparisonTime,
                    true,
                );
                self::assertOwnerAvailable(
                    $transaction,
                    $definition,
                    $authorized->context,
                    $request->comparisonTime,
                    true,
                );
            } catch (SettingException $exception) {
                throw self::apiException($exception);
            }
        };
    }

    public static function hostConfiguration(): ExternalHostConfiguration
    {
        $root = dirname(__DIR__, 3);
        /** @var array{roots: list<string>} $moduleConfig */
        $moduleConfig = require $root . '/backend/config/modules.php';
        /** @var array{tenant: array{clients: non-empty-list<string>}} $authConfig */
        $authConfig = require $root . '/backend/config/auth.php';
        $clientKeys = array_values(array_unique([
            ...$authConfig['tenant']['clients'],
            'platform-web',
        ], SORT_STRING));

        return new ExternalHostConfiguration(
            new ModuleHostLayout(
                'backend/app/Modules',
                'PeanutAdmin\\App\\Modules',
                'frontend/src/modules',
            ),
            $moduleConfig['roots'],
            '/api/v1',
            '/api/platform/v1',
            'docs/api/openapi.yaml',
            'backend/route/openapi-generated.php',
            'packages/web/admin-core/src/generated/api.d.ts',
            $clientKeys,
            'X-Request-Id',
        );
    }

    private static function moduleKey(ManifestDocument $module): string
    {
        $moduleKey = $module->data['key'] ?? null;
        if (!is_string($moduleKey) || $moduleKey === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition owner is invalid.');
        }

        return $moduleKey;
    }

    private static function definitionPath(ManifestDocument $module): ?string
    {
        $backend = $module->data['backend'] ?? null;
        if (!is_array($backend)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition backend metadata is invalid.');
        }
        $relativePath = $backend['setting_definitions'] ?? null;
        if ($relativePath === null) {
            return null;
        }
        if (!is_string($relativePath) || $relativePath === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition path is invalid.');
        }

        return $relativePath;
    }

    private static function ownedResourcePath(ManifestDocument $module, string $relativePath): string
    {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition path is unsafe.');
        }
        $path = realpath($module->root . '/' . $relativePath);
        if ($path === false || !is_file($path) || !str_starts_with($path, $module->root . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition resource is outside its Module.');
        }

        return $path;
    }

    /** @return list<array{module_key: string, resource_key: string, operation: string, target_cardinality: string}> */
    private static function targetDeclarations(ManifestDocument $module): array
    {
        $moduleKey = self::moduleKey($module);
        $catalog = $module->data['catalog'] ?? null;
        if (!is_array($catalog)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target catalog is invalid.');
        }
        $resources = $catalog['protected_resources'] ?? [];
        if (!is_array($resources) || !array_is_list($resources)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target resources are invalid.');
        }
        $declarations = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target resource is invalid.');
            }
            $resourceKey = $resource['key'] ?? null;
            $operations = $resource['operations'] ?? null;
            if (!is_string($resourceKey) || $resourceKey === '' || !is_array($operations)) {
                throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target declaration is invalid.');
            }
            foreach ($operations as $operation) {
                if (!is_array($operation)) {
                    throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target operation is invalid.');
                }
                $operationKey = $operation['key'] ?? null;
                $cardinality = $operation['target_cardinality'] ?? null;
                if (!is_string($operationKey) || $operationKey === '' || !is_string($cardinality)) {
                    throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target operation metadata is invalid.');
                }
                $declarations[] = [
                    'module_key' => $moduleKey,
                    'resource_key' => $resourceKey,
                    'operation' => $operationKey,
                    'target_cardinality' => $cardinality,
                ];
            }
        }

        return $declarations;
    }

    private static function operation(
        string $operationId,
        string $method,
        string $path,
        string $audience,
        string $permission,
        bool $command = false,
    ): ExternalOperationDefinition {
        return new ExternalOperationDefinition(
            $operationId,
            $method,
            $path,
            $audience,
            'peanut.settings',
            new PermissionRequirement($audience, [$permission]),
            atomicCommand: $command,
            idempotencyRequired: $command,
        );
    }

    private static function pdo(): PDO
    {
        return MemberAdminRuntime::pdo();
    }

    private static function admin(PDO $pdo): SettingAdminService
    {
        return new SettingAdminService(
            new PdoSettingRepository($pdo),
            SodiumSecretProtector::fromJson(
                is_string(getenv('PEANUT_SETTINGS_SECRET_KEYS'))
                    ? (string) getenv('PEANUT_SETTINGS_SECRET_KEYS')
                    : '',
                is_string(getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID'))
                    ? (string) getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID')
                    : '',
            ),
        );
    }

    private static function resolver(PDO $pdo): SettingResolver
    {
        $protector = SodiumSecretProtector::fromJson(
            is_string(getenv('PEANUT_SETTINGS_SECRET_KEYS'))
                ? (string) getenv('PEANUT_SETTINGS_SECRET_KEYS')
                : '',
            is_string(getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID'))
                ? (string) getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID')
                : '',
        );

        return new SettingResolver(
            new PdoSettingRepository($pdo),
            $protector,
            new ArrayRevisionedSettingCache(),
        );
    }

    private static function externalRequest(
        Request $request,
        ExternalOperationDefinition $operation,
        string $path,
        string $contextKey,
    ): ExternalOperationRequest {
        $route = $request->route();
        $context = is_array($route) ? ($route[$contextKey] ?? null) : null;
        $requestId = $context instanceof TenantContext || $context instanceof PlatformContext
            ? $context->requestId
            : MemberAdminRuntime::requestId($request);
        $comparisonTime = self::millisecond(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return new ExternalOperationRequest(
            RequestId::fromHeader($requestId),
            $context,
            $operation->method,
            $path,
            [
                'payload' => MemberAdminRuntime::body($request),
                'if_match' => MemberAdminRuntime::header($request, 'if-match'),
                'if_none_match' => MemberAdminRuntime::header($request, 'if-none-match'),
            ],
            [],
            MemberAdminRuntime::header($request, 'idempotency-key'),
            $comparisonTime,
            $comparisonTime->modify('+24 hours'),
        );
    }

    /** @return array<string, mixed> */
    private static function requestPayload(ExternalOperationRequest $request): array
    {
        $payload = $request->body['payload'] ?? null;
        if (!is_array($payload)) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting request body is invalid.');
        }

        return $payload;
    }

    private static function precondition(ExternalOperationRequest $request, string $key): ?string
    {
        $value = $request->body[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private static function requiredIfMatch(ExternalOperationRequest $request): ?string
    {
        if (self::precondition($request, 'if_none_match') !== null) {
            throw SettingException::preconditionRequired();
        }

        return self::precondition($request, 'if_match');
    }

    /**
     * @param array<string, mixed> $body
     * @return array{effectiveAt: DateTimeImmutable, changedFields: non-empty-string}
     */
    private static function unsetInput(array $body, DateTimeImmutable $comparisonTime): array
    {
        $unknown = array_diff(array_keys($body), ['effective_at']);
        if ($unknown !== []) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting unset request contains unknown fields.');
        }

        $effectiveAt = array_key_exists('effective_at', $body)
            ? self::date($body['effective_at'], 'effective_at', false)
            : $comparisonTime->setTimezone(new DateTimeZone('UTC'));
        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', 'effective_at is required.');
        }

        return [
            'effectiveAt' => $effectiveAt,
            'changedFields' => array_key_exists('effective_at', $body) ? 'value,effective_at' : 'value',
        ];
    }

    private static function assertOwnerAvailable(
        PDO $pdo,
        SettingDefinition $definition,
        TenantContext|PlatformContext $context,
        DateTimeImmutable $comparisonTime,
        bool $lockAvailabilityReads = false,
    ): void {
        self::assertModuleAvailable(
            $pdo,
            $definition->moduleKey,
            $context,
            $comparisonTime,
            $lockAvailabilityReads,
        );
    }

    private static function assertModuleAvailable(
        PDO $pdo,
        string $moduleKey,
        TenantContext|PlatformContext $context,
        DateTimeImmutable $comparisonTime,
        bool $lockAvailabilityReads = false,
    ): void {
        $guard = new ModuleGuard(new PdoModuleRuntimeRepository($pdo, $lockAvailabilityReads));
        try {
            $guard->assertDeployment($moduleKey);
            if ($context instanceof TenantContext) {
                $guard->assertTenant($context->tenantId, $moduleKey, $comparisonTime);
            }
        } catch (ModuleException) {
            throw SettingException::notFound();
        }
    }

    private static function ownerAvailable(
        PDO $pdo,
        SettingDefinition $definition,
        TenantContext|PlatformContext $context,
        DateTimeImmutable $comparisonTime,
    ): bool {
        try {
            self::assertOwnerAvailable($pdo, $definition, $context, $comparisonTime);

            return true;
        } catch (SettingException $exception) {
            if ($exception->httpStatus === 404) {
                return false;
            }
            throw $exception;
        }
    }

    /** @return array<string, string> */
    private static function auditMetadata(
        SettingDefinition $definition,
        string $scope,
        string $changedFields,
        string $revision,
    ): array {
        return [
            'module_key' => $definition->moduleKey,
            'setting_key' => $definition->key,
            'scope' => $scope,
            'changed_fields' => $changedFields,
            'revision' => $revision,
        ];
    }

    private static function apiException(SettingException $exception): ApiException
    {
        $detail = match ($exception->httpStatus) {
            404 => 'The requested setting is unavailable.',
            412 => 'The setting revision has changed.',
            422 => 'The setting request is invalid.',
            428 => 'A strong setting precondition is required.',
            503 => 'The settings service is unavailable.',
            default => 'The setting request could not be completed.',
        };

        return new ApiException($exception->errorCode, $exception->httpStatus, $detail);
    }

    private static function httpResponse(ExternalOperationResponse $response, string $requestId): Response
    {
        $headers = [
            'Content-Type' => $response->contentType,
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'no-store',
            ...$response->headers,
        ];
        $etag = $response->body['data']['etag'] ?? null;
        if (is_string($etag)) {
            $headers['ETag'] = $etag;
        }

        return Response::create($response->body, 'json', $response->status)->header($headers);
    }

    private static function date(mixed $value, string $field, bool $nullable): ?DateTimeImmutable
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (!is_string($value)
            || preg_match(
                '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D',
                $value,
                $matches,
            ) !== 1) {
            throw SettingException::invalid(
                'SETTING_INTERVAL_INVALID',
                "{$field} must be an ISO-8601 timestamp.",
            );
        }
        $fraction = str_pad((string) $matches[2], 6, '0');
        if (((int) $fraction) % 1000 !== 0) {
            throw SettingException::invalid(
                'SETTING_INTERVAL_INVALID',
                "{$field} must use millisecond precision.",
            );
        }
        $canonical = $matches[1]
            . '.' . $fraction
            . ($matches[3] === 'Z' ? '+00:00' : $matches[3]);
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.uP',
            $canonical,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i:s.uP') !== $canonical) {
            throw SettingException::invalid(
                'SETTING_INTERVAL_INVALID',
                "{$field} must be an ISO-8601 timestamp.",
            );
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private static function millisecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime(
            (int) $utc->format('H'),
            (int) $utc->format('i'),
            (int) $utc->format('s'),
            (int) $utc->format('v') * 1000,
        );
    }
}
