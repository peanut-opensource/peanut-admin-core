<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\FileMedia\Delivery\ReplayGuard;
use PeanutAdmin\FileMedia\Model\FileDeliveryNonceRecord;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\db\exception\PDOException;

final readonly class ThinkPhpDeliveryReplayGuard implements ReplayGuard
{
    public function __construct(private TenantScope $scope) {}

    public function consume(string $tokenId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        if (preg_match('/^[0-9a-f]{32}$/D', $tokenId) !== 1 || $expiresAt <= $now) {
            return false;
        }
        $utc = new DateTimeZone('UTC');
        try {
            (new FileDeliveryNonceRecord())->save([
                'tenant_id' => $this->scope->tenantId(),
                'token_id_hash' => hash('sha256', $tokenId),
                'expires_at' => $expiresAt->setTimezone($utc)->format('Y-m-d H:i:s.v'),
                'consumed_at' => $now->setTimezone($utc)->format('Y-m-d H:i:s.v'),
            ]);
            return true;
        } catch (PDOException $e) {
            $error = $e->getData()['PDO Error Info'] ?? [];
            if ((string) ($error['SQLSTATE'] ?? $e->getCode()) === '23000'
                && (int) ($error['Driver Error Code'] ?? 0) === 1062
            ) {
                return false;
            }throw $e;
        }
    }
}
