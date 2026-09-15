<?php

declare(strict_types=1);

namespace PeanutAdmin\App\setting;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Settings\Application\EffectiveSetting;
use PeanutAdmin\Settings\Application\SettingAdminService;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Application\SettingResolver;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use think\facade\Db;
use think\Request;
use think\Response;

final readonly class SettingsHttpService
{
    public function __construct(
        private SettingDefinitionCatalog $catalog,
        private SettingAdminService $admin,
        private SettingResolver $resolver,
        private ModuleAvailabilityService $modules,
        private AuditService $audit,
    ) {}

    public function listTenant(Request $request): Response
    {
        $this->assertNoInput($request);
        $context = $this->tenantContext($request);
        $now = self::now();
        $items = [];
        foreach ($this->catalog->registry()->all() as $definition) {
            if (!$definition->allows('tenant') || !$this->ownerAvailable($definition, $context, $now)) {
                continue;
            }
            $items[] = self::item(
                $definition,
                $this->resolver->resolveTenant($definition, $context->tenantId, $now),
            );
        }

        return self::collection($request, $items);
    }

    public function replaceTenant(Request $request, string $moduleKey, string $settingKey): Response
    {
        $context = $this->tenantContext($request);
        $now = self::now();
        $input = self::replaceInput($this->payload($request), $now);
        $definition = $this->catalog->registry()->require($moduleKey, $settingKey);
        $setting = Db::transaction(function () use ($request, $context, $definition, $input, $now): EffectiveSetting {
            $this->assertOwnerAvailable($definition, $context, $now, true);
            $setting = $this->admin->replaceTenant(
                $definition,
                $context->tenantId,
                $context->memberId,
                $input['value'],
                $input['effectiveAt'],
                $input['expiresAt'],
                self::header($request, 'if-match'),
                self::header($request, 'if-none-match'),
                $now,
            );
            $this->audit->tenantMember(
                $context,
                'setting.tenant.replaced',
                'peanut.settings.tenant.replace',
                'setting',
                $definition->qualifiedKey(),
                self::auditMetadata($definition, 'tenant', $input['changedFields'], $setting->revision),
            );

            return $setting;
        });

        return self::itemResponse($request, self::item($definition, $setting));
    }

    public function unsetTenant(Request $request, string $moduleKey, string $settingKey): Response
    {
        $context = $this->tenantContext($request);
        $now = self::now();
        $input = self::unsetInput($this->payload($request), $now);
        if (self::header($request, 'if-none-match') !== null) {
            throw SettingException::preconditionRequired();
        }
        $definition = $this->catalog->registry()->require($moduleKey, $settingKey);
        $setting = Db::transaction(function () use ($request, $context, $definition, $input, $now): EffectiveSetting {
            $this->assertOwnerAvailable($definition, $context, $now, true);
            $setting = $this->admin->unsetTenant(
                $definition,
                $context->tenantId,
                $context->memberId,
                $input['effectiveAt'],
                self::header($request, 'if-match'),
                $now,
            );
            $this->audit->tenantMember(
                $context,
                'setting.tenant.unset',
                'peanut.settings.tenant.unset',
                'setting',
                $definition->qualifiedKey(),
                self::auditMetadata($definition, 'tenant', $input['changedFields'], $setting->revision),
            );

            return $setting;
        });

        return self::itemResponse($request, self::item($definition, $setting));
    }

    public function listDeployment(Request $request): Response
    {
        $this->assertNoInput($request);
        $this->platformContext($request);
        $now = self::now();
        $this->assertDeployment('peanut.settings');
        $items = [];
        foreach ($this->catalog->registry()->all() as $definition) {
            if (!$definition->allows('deployment') || !$this->ownerAvailable($definition, null, $now)) {
                continue;
            }
            $items[] = self::item($definition, $this->resolver->resolveDeployment($definition, $now));
        }

        return self::collection($request, $items);
    }

    public function replaceDeployment(Request $request, string $moduleKey, string $settingKey): Response
    {
        $context = $this->platformContext($request);
        $now = self::now();
        $input = self::replaceInput($this->payload($request), $now);
        $definition = $this->catalog->registry()->require($moduleKey, $settingKey);
        $setting = Db::transaction(function () use ($request, $context, $definition, $input, $now): EffectiveSetting {
            $this->modules->assertDeployment('peanut.settings', true);
            $this->assertOwnerAvailable($definition, null, $now, true);
            $setting = $this->admin->replaceDeployment(
                $definition,
                $input['value'],
                $context->operatorId,
                $input['effectiveAt'],
                $input['expiresAt'],
                self::header($request, 'if-match'),
                self::header($request, 'if-none-match'),
                $now,
            );
            $this->audit->platform(
                $context->operatorId,
                $context->accountId,
                $context->requestId,
                'setting.deployment.replaced',
                'peanut.settings.deployment.replace',
                self::auditMetadata($definition, 'deployment', $input['changedFields'], $setting->revision),
            );

            return $setting;
        });

        return self::itemResponse($request, self::item($definition, $setting));
    }

    public function unsetDeployment(Request $request, string $moduleKey, string $settingKey): Response
    {
        $context = $this->platformContext($request);
        $now = self::now();
        $input = self::unsetInput($this->payload($request), $now);
        if (self::header($request, 'if-none-match') !== null) {
            throw SettingException::preconditionRequired();
        }
        $definition = $this->catalog->registry()->require($moduleKey, $settingKey);
        $setting = Db::transaction(function () use ($request, $context, $definition, $input, $now): EffectiveSetting {
            $this->modules->assertDeployment('peanut.settings', true);
            $this->assertOwnerAvailable($definition, null, $now, true);
            $setting = $this->admin->unsetDeployment(
                $definition,
                $context->operatorId,
                $input['effectiveAt'],
                self::header($request, 'if-match'),
                $now,
            );
            $this->audit->platform(
                $context->operatorId,
                $context->accountId,
                $context->requestId,
                'setting.deployment.unset',
                'peanut.settings.deployment.unset',
                self::auditMetadata($definition, 'deployment', $input['changedFields'], $setting->revision),
            );

            return $setting;
        });

        return self::itemResponse($request, self::item($definition, $setting));
    }

    private function assertOwnerAvailable(
        SettingDefinition $definition,
        ?TenantContext $context,
        DateTimeImmutable $now,
        bool $lock = false,
    ): void {
        try {
            $this->modules->assertDeployment($definition->moduleKey, $lock);
            if ($context instanceof TenantContext) {
                $this->modules->assertTenant(
                    TenantScope::fromTrustedContext($context->tenantId, $context->requestId),
                    $definition->moduleKey,
                    $now,
                    $lock,
                );
            }
        } catch (ModuleException) {
            throw SettingException::notFound();
        }
    }

    private function ownerAvailable(
        SettingDefinition $definition,
        ?TenantContext $context,
        DateTimeImmutable $now,
    ): bool {
        try {
            $this->assertOwnerAvailable($definition, $context, $now);

            return true;
        } catch (SettingException $exception) {
            if ($exception->httpStatus === 404) {
                return false;
            }
            throw $exception;
        }
    }

    private function assertDeployment(string $moduleKey): void
    {
        try {
            $this->modules->assertDeployment($moduleKey);
        } catch (ModuleException) {
            throw SettingException::notFound();
        }
    }

    private function tenantContext(Request $request): TenantContext
    {
        return MemberAdminRuntime::context($request);
    }

    private function platformContext(Request $request): PlatformContext
    {
        $route = $request->route();
        $context = is_array($route) ? ($route['platform_context'] ?? null) : null;
        if (!$context instanceof PlatformContext) {
            throw SettingException::notFound('SETTING_ACTOR_UNAUTHORIZED');
        }

        return $context;
    }

    /** @return array<string,mixed> */
    private function payload(Request $request): array
    {
        if ($request->get() !== []) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting request does not accept query parameters.');
        }
        $payload = MemberAdminRuntime::body($request);

        return $payload;
    }

    private function assertNoInput(Request $request): void
    {
        if (MemberAdminRuntime::body($request) !== [] || $request->get() !== []) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting list request does not accept input.');
        }
    }

    /** @param array<string,mixed> $body
     * @return array{value:mixed,effectiveAt:DateTimeImmutable,expiresAt:?DateTimeImmutable,changedFields:string}
     */
    private static function replaceInput(array $body, DateTimeImmutable $comparisonTime): array
    {
        if (array_diff(array_keys($body), ['value', 'effective_at', 'expires_at']) !== []
            || !array_key_exists('value', $body)) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting replacement request is invalid.');
        }
        $effectiveAt = array_key_exists('effective_at', $body)
            ? self::date($body['effective_at'], 'effective_at', false)
            : $comparisonTime;
        $expiresAt = array_key_exists('expires_at', $body)
            ? self::date($body['expires_at'], 'expires_at', true)
            : null;
        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', 'effective_at is required.');
        }
        SettingAdminService::assertValidInterval($effectiveAt, $expiresAt);
        $changed = ['value'];
        if (array_key_exists('effective_at', $body)) {
            $changed[] = 'effective_at';
        }
        if (array_key_exists('expires_at', $body)) {
            $changed[] = 'expires_at';
        }

        return [
            'value' => $body['value'],
            'effectiveAt' => $effectiveAt,
            'expiresAt' => $expiresAt,
            'changedFields' => implode(',', $changed),
        ];
    }

    /** @param array<string,mixed> $body
     * @return array{effectiveAt:DateTimeImmutable,changedFields:string}
     */
    private static function unsetInput(array $body, DateTimeImmutable $comparisonTime): array
    {
        if (array_diff(array_keys($body), ['effective_at']) !== []) {
            throw SettingException::invalid('SETTING_REQUEST_INVALID', 'The setting unset request is invalid.');
        }
        $effectiveAt = array_key_exists('effective_at', $body)
            ? self::date($body['effective_at'], 'effective_at', false)
            : $comparisonTime;
        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', 'effective_at is required.');
        }
        SettingAdminService::assertValidInterval($effectiveAt, null);

        return [
            'effectiveAt' => $effectiveAt,
            'changedFields' => array_key_exists('effective_at', $body) ? 'value,effective_at' : 'value',
        ];
    }

    private static function date(mixed $value, string $field, bool $nullable): ?DateTimeImmutable
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (!is_string($value) || preg_match(
            '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D',
            $value,
            $matches,
        ) !== 1) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', "{$field} must be an ISO-8601 timestamp.");
        }
        $fraction = str_pad((string) $matches[2], 6, '0');
        if (((int) $fraction) % 1000 !== 0) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', "{$field} must use millisecond precision.");
        }
        $canonical = $matches[1] . '.' . $fraction . ($matches[3] === 'Z' ? '+00:00' : $matches[3]);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $canonical, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i:s.uP') !== $canonical) {
            throw SettingException::invalid('SETTING_INTERVAL_INVALID', "{$field} must be an ISO-8601 timestamp.");
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string,mixed> */
    private static function item(SettingDefinition $definition, EffectiveSetting $setting): array
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

    /** @param list<array<string,mixed>> $items */
    private static function collection(Request $request, array $items): Response
    {
        try {
            $encoded = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw SettingException::unavailable('SETTING_RESPONSE_INVALID', 'The settings response cannot be encoded.');
        }

        return Response::create([
            'data' => ['items' => $items],
            'meta' => ['request_id' => MemberAdminRuntime::requestId($request)],
        ], 'json', 200)->header([
            'Content-Type' => 'application/json',
            'X-Request-Id' => MemberAdminRuntime::requestId($request),
            'Cache-Control' => 'no-store',
            'ETag' => '"settings-' . hash('sha256', $encoded) . '"',
        ]);
    }

    /** @param array<string,mixed> $item */
    private static function itemResponse(Request $request, array $item): Response
    {
        return Response::create([
            'data' => $item,
            'meta' => ['request_id' => MemberAdminRuntime::requestId($request)],
        ], 'json', 200)->header([
            'Content-Type' => 'application/json',
            'X-Request-Id' => MemberAdminRuntime::requestId($request),
            'Cache-Control' => 'no-store',
            'ETag' => (string) $item['etag'],
        ]);
    }

    /** @return array<string,string> */
    private static function auditMetadata(
        SettingDefinition $definition,
        string $scope,
        string $changedFields,
        int $revision,
    ): array {
        return [
            'module_key' => $definition->moduleKey,
            'setting_key' => $definition->key,
            'scope' => $scope,
            'changed_fields' => $changedFields,
            'revision' => (string) $revision,
        ];
    }

    private static function header(Request $request, string $name): ?string
    {
        return MemberAdminRuntime::header($request, $name);
    }

    private static function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            (int) $now->format('s'),
            (int) $now->format('v') * 1000,
        );
    }
}
