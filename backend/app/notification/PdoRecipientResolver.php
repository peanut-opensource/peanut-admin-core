<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\NotificationSms\Application\NotificationException;
use PeanutAdmin\NotificationSms\Application\RecipientResolver;
use PeanutAdmin\NotificationSms\Application\RecipientSnapshot;
use PeanutAdmin\NotificationSms\Sms\SmsRecipient;
use PeanutAdmin\NotificationSms\Sms\SmsRecipientResolver;

final readonly class PdoRecipientResolver implements RecipientResolver, SmsRecipientResolver
{
    /** @param array<string, mixed> $directory */
    public function __construct(private PDO $pdo, private array $directory, private string $digestKey) {}

    public function snapshot(TenantContext $context, int $memberId, bool $requiresSms): RecipientSnapshot
    {
        $row = $this->member($context->tenantId, $memberId);
        $sms = $requiresSms ? $this->resolve($context->tenantId, $memberId) : null;
        return new RecipientSnapshot(
            (int) $row['id'],
            (int) $row['account_id'],
            (string) $row['display_name'],
            $sms?->masked,
            $sms?->digest,
        );
    }

    public function resolve(int $tenantId, int $memberId): SmsRecipient
    {
        $this->member($tenantId, $memberId);
        $number = $this->directory[$tenantId . ':' . $memberId] ?? null;
        if (!is_string($number) || strlen($this->digestKey) < 32) {
            throw NotificationException::recipientUnavailable();
        }
        return new SmsRecipient($number, $this->digestKey . ':' . $tenantId);
    }

    /** @return array<string, mixed> */
    private function member(int $tenantId, int $memberId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tm.id, tm.account_id, COALESCE(NULLIF(tm.display_name, ''), a.display_name) AS display_name
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
WHERE tm.tenant_id = :tenant_id AND tm.id = :member_id AND tm.status = 'active'
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw NotificationException::recipientUnavailable();
        }
        return $row;
    }
}
