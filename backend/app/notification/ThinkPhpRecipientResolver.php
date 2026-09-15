<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\NotificationSms\Application\NotificationException;
use PeanutAdmin\NotificationSms\Application\RecipientResolver;
use PeanutAdmin\NotificationSms\Application\RecipientSnapshot;
use PeanutAdmin\NotificationSms\Sms\SmsRecipient;
use PeanutAdmin\NotificationSms\Sms\SmsRecipientResolver;
use PeanutAdmin\Kernel\Persistence\Model\TenantMember;
use think\facade\Db;

final readonly class ThinkPhpRecipientResolver implements RecipientResolver, SmsRecipientResolver
{
    /** @param array<string, mixed> $directory */
    public function __construct(private array $directory, private string $digestKey) {}

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
        $row = TenantMember::alias('member')
            ->join('account account', 'account.id = member.account_id')
            ->where('member.tenant_id', $tenantId)
            ->where('member.id', $memberId)
            ->where('member.status', 'active')
            ->where('account.status', 'active')
            ->field([
                'member.id',
                'member.account_id',
                'display_name' => Db::raw("COALESCE(NULLIF(member.display_name, ''), account.display_name)"),
            ])
            ->find();
        if (!is_array($row)) {
            throw NotificationException::recipientUnavailable();
        }

        return $row;
    }
}
