<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\FileMedia\Delivery\ReplayGuard;
use think\db\exception\PDOException;
use think\db\PDOConnection;

final readonly class ThinkPhpDeliveryReplayGuard implements ReplayGuard
{
    public function __construct(private PDOConnection $connection, private int $tenantId) {}

    public function consume(string $tokenId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        if ($this->tenantId < 1 || preg_match('/^[0-9a-f]{32}$/D', $tokenId) !== 1 || $expiresAt <= $now) {
            return false;
        }
        $utc = new DateTimeZone('UTC');
        try {
            $this->connection->execute('INSERT INTO pa_file_delivery_nonce (tenant_id,token_id_hash,expires_at,consumed_at) VALUES (:tenant_id,:token_hash,:expires_at,:consumed_at)', ['tenant_id' => $this->tenantId,'token_hash' => hash('sha256', $tokenId),'expires_at' => $expiresAt->setTimezone($utc)->format('Y-m-d H:i:s.v'),'consumed_at' => $now->setTimezone($utc)->format('Y-m-d H:i:s.v')]);
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
