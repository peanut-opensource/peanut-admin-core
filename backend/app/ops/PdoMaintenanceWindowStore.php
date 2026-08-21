<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;

final readonly class PdoMaintenanceWindowStore implements MaintenanceWindowStore
{
    public function __construct(private PDO $pdo) {}
    public function current(PlatformContext $context): ?MaintenanceWindow
    {
        $r = $this->one("SELECT * FROM pa_ops_maintenance_window WHERE state IN ('scheduled','active') ORDER BY id DESC LIMIT 1", []);
        return $r === null ? null : $this->map($r);
    }
    public function schedule(PlatformContext $context, MaintenanceWindow $candidate, int $expectedRevision, string $idempotencyDigest, string $requestDigest, OpsAuditEvent $audit): MaintenanceWindow
    {
        return (new PdoTransactionManager($this->pdo))->run(function () use ($context, $candidate, $expectedRevision, $idempotencyDigest, $requestDigest, $audit) {
            $replay = $this->one('SELECT * FROM pa_ops_maintenance_window WHERE created_by_operator_id=:operator AND idempotency_digest=:digest FOR UPDATE', ['operator' => $context->operatorId,'digest' => $idempotencyDigest]);
            if ($replay !== null) {
                if (!hash_equals((string) $replay['request_digest'], $requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }return $this->map($replay);
            } $active = $this->one("SELECT * FROM pa_ops_maintenance_window WHERE state IN ('scheduled','active') LIMIT 1 FOR UPDATE", []);
            if (($active === null && $expectedRevision !== 0) || ($active !== null && (int) $active['revision'] !== $expectedRevision)) {
                throw OpsConsoleException::revisionConflict();
            }if ($active !== null) {
                throw OpsConsoleException::operationInProgress();
            }$s = $this->pdo->prepare('INSERT INTO pa_ops_maintenance_window (maintenance_key,state,reason_key,starts_at,ends_at,revision,idempotency_digest,request_digest,created_by_operator_id,created_at,updated_at) VALUES (:key,:state,:reason,:starts,:ends,1,:idempotency,:request,:operator,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))');
            $s->execute(['key' => $candidate->maintenanceKey,'state' => $candidate->state,'reason' => $candidate->reasonKey,'starts' => $this->db($candidate->startsAt),'ends' => $this->db($candidate->endsAt),'idempotency' => $idempotencyDigest,'request' => $requestDigest,'operator' => $context->operatorId]);
            $this->audit($context, $audit);
            return $this->map($this->one('SELECT * FROM pa_ops_maintenance_window WHERE maintenance_key=:key', ['key' => $candidate->maintenanceKey]) ?? throw OpsConsoleException::internal());
        });
    }
    public function close(PlatformContext $context, string $maintenanceKey, int $expectedRevision, string $idempotencyDigest, string $requestDigest, OpsAuditEvent $audit): MaintenanceWindow
    {
        return (new PdoTransactionManager($this->pdo))->run(function () use ($context, $maintenanceKey, $expectedRevision, $idempotencyDigest, $requestDigest, $audit) {
            $row = $this->one('SELECT * FROM pa_ops_maintenance_window WHERE maintenance_key=:key FOR UPDATE', ['key' => $maintenanceKey]);
            if ($row === null || (int) $row['revision'] !== $expectedRevision || $row['state'] === 'closed') {
                throw OpsConsoleException::revisionConflict();
            }$s = $this->pdo->prepare("UPDATE pa_ops_maintenance_window SET state='closed',revision=revision+1,idempotency_digest=:idempotency,request_digest=:request,closed_at=UTC_TIMESTAMP(3),updated_at=UTC_TIMESTAMP(3) WHERE id=:id AND revision=:revision");
            $s->execute(['idempotency' => $idempotencyDigest,'request' => $requestDigest,'id' => $row['id'],'revision' => $expectedRevision]);
            $this->audit($context, $audit);
            return $this->map($this->one('SELECT * FROM pa_ops_maintenance_window WHERE id=:id', ['id' => $row['id']]) ?? throw OpsConsoleException::internal());
        });
    }
    private function audit(PlatformContext $c, OpsAuditEvent $a): void
    {
        (new PdoAuditRepository($this->pdo))->appendPlatform($a->eventType, $a->action, $c->requestId, $c->operatorId, $c->accountId, $a->metadata);
    }
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? $r : null;
    }
    /** @param array<string, mixed> $r */
    private function map(array $r): MaintenanceWindow
    {
        return new MaintenanceWindow((string) $r['maintenance_key'], (string) $r['state'], (string) $r['reason_key'], $this->iso((string) $r['starts_at']), $this->iso((string) $r['ends_at']), (int) $r['revision']);
    }
    private function iso(string $v): string
    {
        return str_replace(' ', 'T', $v) . 'Z';
    }private function db(string $v): string
    {
        return (new \DateTimeImmutable($v))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
