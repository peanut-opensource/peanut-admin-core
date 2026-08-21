<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use RuntimeException;

final class WorkspaceContextRuntime
{
    private function __construct() {}

    /** @return array<string, mixed> */
    public static function tenant(PDO $pdo, TenantContext $context): array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT
    account.id AS account_id,
    account.display_name AS account_display_name,
    account.avatar_uri,
    tenant.id AS tenant_id,
    tenant.code AS tenant_code,
    tenant.display_name AS tenant_display_name,
    tenant.timezone,
    member.id AS member_id,
    COALESCE(member.display_name, account.display_name) AS member_display_name,
    member.primary_department_id,
    COALESCE(GROUP_CONCAT(DISTINCT role.id ORDER BY role.id SEPARATOR ','), '') AS role_ids
FROM pa_tenant_member member
JOIN pa_account account ON account.id = member.account_id
JOIN pa_tenant tenant ON tenant.id = member.tenant_id
LEFT JOIN pa_member_role member_role
  ON member_role.tenant_id = member.tenant_id
 AND member_role.tenant_member_id = member.id
LEFT JOIN pa_role role
  ON role.tenant_id = member_role.tenant_id
 AND role.id = member_role.role_id
 AND role.status = 'active'
WHERE member.tenant_id = :tenant_id
  AND member.id = :member_id
  AND member.account_id = :account_id
  AND member.status = 'active'
  AND tenant.status = 'active'
  AND account.status = 'active'
GROUP BY
    account.id, account.display_name, account.avatar_uri,
    tenant.id, tenant.code, tenant.display_name, tenant.timezone,
    member.id, member.display_name, member.primary_department_id
SQL);
        if ($statement === false) {
            throw new RuntimeException('Could not prepare the tenant context view.');
        }
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'member_id' => $context->memberId,
            'account_id' => $context->accountId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('The tenant context view is unavailable.');
        }

        $permissionKeys = (new PdoTenantAuthorizationRepository($pdo))
            ->permissions($context->tenantId, $context->memberId)
            ->keys();
        sort($permissionKeys, SORT_STRING);
        $moduleKeys = [
            'core',
            ...(new PdoMenuCatalogRepository($pdo))->activeTenantModules($context->tenantId),
        ];
        $moduleKeys = array_values(array_unique($moduleKeys));
        sort($moduleKeys, SORT_STRING);

        return [
            'audience' => 'tenant',
            'account' => [
                'id' => (string) $row['account_id'],
                'display_name' => (string) $row['account_display_name'],
                'avatar_uri' => $row['avatar_uri'] === null ? null : (string) $row['avatar_uri'],
            ],
            'tenant' => [
                'id' => (string) $row['tenant_id'],
                'code' => (string) $row['tenant_code'],
                'display_name' => (string) $row['tenant_display_name'],
                'timezone' => (string) $row['timezone'],
            ],
            'member' => [
                'id' => (string) $row['member_id'],
                'display_name' => (string) $row['member_display_name'],
                'primary_department_id' => $row['primary_department_id'] === null
                    ? null
                    : (string) $row['primary_department_id'],
                'role_ids' => self::csvStrings((string) $row['role_ids']),
            ],
            'module_keys' => $moduleKeys,
            'permission_keys' => $permissionKeys,
            'authorization_revision' => (string) $context->authorizationRevision,
        ];
    }

    /** @return array<string, mixed> */
    public static function platform(PDO $pdo, PlatformContext $context): array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT
    account.id AS account_id,
    account.display_name AS account_display_name,
    account.avatar_uri,
    operator.id AS operator_id,
    COALESCE(operator.display_name, account.display_name) AS operator_display_name
FROM pa_platform_operator operator
JOIN pa_account account ON account.id = operator.account_id
WHERE operator.id = :operator_id
  AND operator.account_id = :account_id
  AND operator.status = 'active'
  AND account.status = 'active'
SQL);
        if ($statement === false) {
            throw new RuntimeException('Could not prepare the platform context view.');
        }
        $statement->execute([
            'operator_id' => $context->operatorId,
            'account_id' => $context->accountId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('The platform context view is unavailable.');
        }

        $authorization = new PdoPlatformAuthorizationRepository($pdo);
        $permissionKeys = $authorization->permissions($context->operatorId)->keys();
        sort($permissionKeys, SORT_STRING);

        return [
            'audience' => 'platform',
            'account' => [
                'id' => (string) $row['account_id'],
                'display_name' => (string) $row['account_display_name'],
                'avatar_uri' => $row['avatar_uri'] === null ? null : (string) $row['avatar_uri'],
            ],
            'operator' => [
                'id' => (string) $row['operator_id'],
                'display_name' => (string) $row['operator_display_name'],
            ],
            'permission_keys' => $permissionKeys,
            'authorization_revision' => $authorization->revision($context->operatorId),
        ];
    }

    /** @return list<string> */
    private static function csvStrings(string $value): array
    {
        return $value === ''
            ? []
            : array_values(array_filter(
                explode(',', $value),
                static fn(string $item): bool => $item !== '',
            ));
    }
}
