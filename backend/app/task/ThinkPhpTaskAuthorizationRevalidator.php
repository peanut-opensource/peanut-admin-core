<?php

declare(strict_types=1);

namespace PeanutAdmin\App\task;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\Kernel\Async\AsyncAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\VerifiedJobEnvelope;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationRepository;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleRuntimeRepository;
use PeanutAdmin\Kernel\Persistence\Model\TenantMember;

final readonly class ThinkPhpTaskAuthorizationRevalidator implements AsyncAuthorizationRevalidator
{
    public function __construct(
        private ModuleRuntimeRepository $modules,
        private TenantAuthorizationRepository $authorization,
    ) {}

    public function reauthorize(VerifiedJobEnvelope $envelope): AuthorizedOperationContext
    {
        if (!hash_equals('peanut.notification-sms', $envelope->resourceKey)
            || !hash_equals('manage', $envelope->operation)
            || preg_match('/^job_[0-9a-f]{32}$/D', $envelope->operationId) !== 1) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        (new ModuleGuard($this->modules))->assertTenant($envelope->tenantId, 'peanut.notification-sms', $now);
        $revision = TenantMember::alias('member')
            ->join('account account', "account.id = member.account_id AND account.status = 'active'")
            ->join('tenant tenant', "tenant.id = member.tenant_id AND tenant.status = 'active'")
            ->join('module_installation installation', "installation.module_key = 'peanut.notification-sms' AND installation.status = 'active'")
            ->join('tenant_module module', "module.tenant_id = member.tenant_id AND module.module_key = installation.module_key AND module.status = 'enabled'")
            ->where('member.tenant_id', $envelope->tenantId)->where('member.id', $envelope->memberId)
            ->where('member.account_id', $envelope->accountId)->where('member.status', 'active')
            ->value('member.authorization_revision');
        if (!is_int($revision) && !(is_string($revision) && ctype_digit($revision))) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        if (!$this->authorization->permissions($envelope->tenantId, $envelope->memberId)
            ->allows('peanut.notification-sms.manage')) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        $context = TenantContext::fromValidatedSession(new ValidatedTenantSession(
            1, 'async-job', $envelope->tenantId, $envelope->accountId, $envelope->memberId,
            'admin-web', $now, (int) $revision,
        ), $envelope->traceId);

        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $context, $envelope->resourceKey, $envelope->operation, $envelope->requestedTargets,
            $this->authorization->revision($envelope->tenantId, $envelope->memberId),
        ));
    }
}
