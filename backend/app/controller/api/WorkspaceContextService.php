<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationRepository;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Menu\MenuCatalogRepository;
use PeanutAdmin\Kernel\Persistence\Model\PlatformOperator;
use PeanutAdmin\Kernel\Persistence\Model\TenantMember;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationRepository;
use RuntimeException;

final readonly class WorkspaceContextService
{
    public function __construct(
        private TenantAuthorizationRepository $tenantAuthorization,
        private PlatformAuthorizationRepository $platformAuthorization,
        private MenuCatalogRepository $menus,
    ) {}

    /** @return array<string, mixed> */
    public function tenant(TenantContext $context): array
    {
        $row = TenantMember::alias('member')
            ->join('account account', 'account.id = member.account_id')
            ->join('tenant tenant', 'tenant.id = member.tenant_id')
            ->where('member.tenant_id', $context->tenantId)
            ->where('member.id', $context->memberId)
            ->where('member.account_id', $context->accountId)
            ->where('member.status', 'active')
            ->where('tenant.status', 'active')
            ->where('account.status', 'active')
            ->field([
                'account.id' => 'account_id', 'account.display_name' => 'account_display_name',
                'account.avatar_uri', 'tenant.id' => 'tenant_id', 'tenant.code' => 'tenant_code',
                'tenant.display_name' => 'tenant_display_name', 'tenant.timezone',
                'member.id' => 'member_id', 'member.display_name' => 'member_display_name',
                'member.primary_department_id',
            ])
            ->find();
        if ($row === null) {
            throw new RuntimeException('The tenant context view is unavailable.');
        }
        $roleIds = TenantMember::alias('member_role')
            ->table('pa_member_role')
            ->join(
                'role role',
                "role.tenant_id = member_role.tenant_id AND role.id = member_role.role_id AND role.status = 'active'",
            )
            ->where('member_role.tenant_id', $context->tenantId)
            ->where('member_role.tenant_member_id', $context->memberId)
            ->order('role.id')
            ->column('role.id');
        $permissionKeys = $this->tenantAuthorization
            ->permissions($context->tenantId, $context->memberId)
            ->keys();
        sort($permissionKeys, SORT_STRING);
        $moduleKeys = ['core', ...$this->menus->activeTenantModules($context->tenantId)];
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
                'display_name' => $row['member_display_name'] === null
                    ? (string) $row['account_display_name']
                    : (string) $row['member_display_name'],
                'primary_department_id' => $row['primary_department_id'] === null
                    ? null
                    : (string) $row['primary_department_id'],
                'role_ids' => array_map('strval', $roleIds),
            ],
            'module_keys' => $moduleKeys,
            'permission_keys' => $permissionKeys,
            'authorization_revision' => (string) $context->authorizationRevision,
        ];
    }

    /** @return array<string, mixed> */
    public function platform(PlatformContext $context): array
    {
        $row = PlatformOperator::alias('operator')
            ->join('account account', 'account.id = operator.account_id')
            ->where('operator.id', $context->operatorId)
            ->where('operator.account_id', $context->accountId)
            ->where('operator.status', 'active')
            ->where('account.status', 'active')
            ->field([
                'account.id' => 'account_id', 'account.display_name' => 'account_display_name',
                'account.avatar_uri', 'operator.id' => 'operator_id',
                'operator.display_name' => 'operator_display_name',
            ])
            ->find();
        if ($row === null) {
            throw new RuntimeException('The platform context view is unavailable.');
        }
        $permissionKeys = $this->platformAuthorization->permissions($context->operatorId)->keys();
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
                'display_name' => $row['operator_display_name'] === null
                    ? (string) $row['account_display_name']
                    : (string) $row['operator_display_name'],
            ],
            'permission_keys' => $permissionKeys,
            'authorization_revision' => $this->platformAuthorization->revision($context->operatorId),
        ];
    }
}
