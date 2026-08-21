<?php

declare(strict_types=1);

namespace PeanutAdmin\App\task;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Async\AsyncAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\VerifiedJobEnvelope;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;

final readonly class PdoTaskAuthorizationRevalidator implements AsyncAuthorizationRevalidator
{
    public function __construct(private PDO $pdo) {}

    public function reauthorize(VerifiedJobEnvelope $envelope): AuthorizedOperationContext
    {
        if (!hash_equals('peanut.notification-sms', $envelope->resourceKey) || !hash_equals('manage', $envelope->operation)) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        if (preg_match('/^job_[0-9a-f]{32}$/D', $envelope->operationId) !== 1) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        (new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo)))->assertTenant(
            $envelope->tenantId,
            'peanut.notification-sms',
            $now,
        );
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tm.authorization_revision
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
JOIN pa_tenant t ON t.id = tm.tenant_id AND t.status = 'active'
JOIN pa_module_installation mi ON mi.module_key = 'peanut.notification-sms' AND mi.status = 'active'
JOIN pa_tenant_module tmi ON tmi.tenant_id = tm.tenant_id AND tmi.module_key = mi.module_key AND tmi.status = 'enabled'
WHERE tm.tenant_id = :tenant_id AND tm.id = :member_id AND tm.account_id = :account_id AND tm.status = 'active'
SQL);
        $statement->execute(['tenant_id' => $envelope->tenantId,'member_id' => $envelope->memberId,'account_id' => $envelope->accountId]);
        $revision = $statement->fetchColumn();
        if (!is_int($revision) && !(is_string($revision) && ctype_digit($revision))) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        $repository = new PdoTenantAuthorizationRepository($this->pdo);
        if (!$repository->permissions($envelope->tenantId, $envelope->memberId)->allows('peanut.notification-sms.manage')) {
            throw new AuthException('CONTEXT_SYSTEM_ACTOR_INVALID', 403);
        }
        $context = TenantContext::fromValidatedSession(new ValidatedTenantSession(1, 'async-job', $envelope->tenantId, $envelope->accountId, $envelope->memberId, 'admin-web', $now, (int) $revision), $envelope->traceId);
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow($context, $envelope->resourceKey, $envelope->operation, $envelope->requestedTargets, $repository->revision($envelope->tenantId, $envelope->memberId)));
    }
}
