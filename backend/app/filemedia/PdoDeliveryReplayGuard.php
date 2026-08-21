<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use PeanutAdmin\FileMedia\Delivery\ReplayGuard;

final readonly class PdoDeliveryReplayGuard implements ReplayGuard
{
    public function __construct(private PDO $pdo, private int $tenantId) {}

    public function consume(string $tokenId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        if ($this->tenantId < 1 || preg_match('/^[0-9a-f]{32}$/D', $tokenId) !== 1 || $expiresAt <= $now) {
            return false;
        }
        $statement = $this->pdo->prepare('INSERT INTO pa_file_delivery_nonce (tenant_id,token_id_hash,expires_at,consumed_at) VALUES (:tenant_id,:token_hash,:expires_at,:consumed_at)');
        $utc = new DateTimeZone('UTC');
        try {
            $statement->execute(['tenant_id' => $this->tenantId,'token_hash' => hash('sha256', $tokenId),'expires_at' => $expiresAt->setTimezone($utc)->format('Y-m-d H:i:s.v'),'consumed_at' => $now->setTimezone($utc)->format('Y-m-d H:i:s.v')]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }throw $e;
        }
    }
}
