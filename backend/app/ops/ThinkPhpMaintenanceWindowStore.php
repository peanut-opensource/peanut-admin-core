<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;
use think\facade\Db;

final readonly class ThinkPhpMaintenanceWindowStore implements MaintenanceWindowStore
{
    public function __construct(private AuditService $audit) {}

    public function current(PlatformContext $context): ?MaintenanceWindow
    {
        $row = Db::name('ops_maintenance_window')->whereIn('state', ['scheduled', 'active'])
            ->order('id', 'desc')->find();

        return $row === null ? null : $this->map($row);
    }

    public function schedule(
        PlatformContext $context,
        MaintenanceWindow $candidate,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        return Db::transaction(function () use (
            $context, $candidate, $expectedRevision, $idempotencyDigest, $requestDigest, $audit,
        ): MaintenanceWindow {
            $replay = Db::name('ops_maintenance_window')->where('created_by_operator_id', $context->operatorId)
                ->where('idempotency_digest', $idempotencyDigest)->lock(true)->find();
            if ($replay !== null) {
                if (!hash_equals((string) $replay['request_digest'], $requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }

                return $this->map($replay);
            }
            $active = Db::name('ops_maintenance_window')->whereIn('state', ['scheduled', 'active'])->lock(true)->find();
            if (($active === null && $expectedRevision !== 0)
                || ($active !== null && (int) $active['revision'] !== $expectedRevision)) {
                throw OpsConsoleException::revisionConflict();
            }
            if ($active !== null) {
                throw OpsConsoleException::operationInProgress();
            }
            $now = Db::raw('UTC_TIMESTAMP(3)');
            Db::name('ops_maintenance_window')->insert([
                'maintenance_key' => $candidate->maintenanceKey, 'state' => $candidate->state,
                'reason_key' => $candidate->reasonKey, 'starts_at' => $this->db($candidate->startsAt),
                'ends_at' => $this->db($candidate->endsAt), 'revision' => 1,
                'idempotency_digest' => $idempotencyDigest, 'request_digest' => $requestDigest,
                'created_by_operator_id' => $context->operatorId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->recordAudit($context, $audit);

            return $this->find($candidate->maintenanceKey);
        });
    }

    public function close(
        PlatformContext $context,
        string $maintenanceKey,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        return Db::transaction(function () use (
            $context, $maintenanceKey, $expectedRevision, $idempotencyDigest, $requestDigest, $audit,
        ): MaintenanceWindow {
            $row = Db::name('ops_maintenance_window')->where('maintenance_key', $maintenanceKey)->lock(true)->find();
            if ($row === null || (int) $row['revision'] !== $expectedRevision || $row['state'] === 'closed') {
                throw OpsConsoleException::revisionConflict();
            }
            if (Db::name('ops_maintenance_window')->where('id', (int) $row['id'])
                ->where('revision', $expectedRevision)->update([
                    'state' => 'closed', 'revision' => Db::raw('revision + 1'),
                    'idempotency_digest' => $idempotencyDigest, 'request_digest' => $requestDigest,
                    'closed_at' => Db::raw('UTC_TIMESTAMP(3)'), 'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
                ]) !== 1) {
                throw OpsConsoleException::revisionConflict();
            }
            $this->recordAudit($context, $audit);

            return $this->find($maintenanceKey);
        });
    }

    private function find(string $key): MaintenanceWindow
    {
        $row = Db::name('ops_maintenance_window')->where('maintenance_key', $key)->find();

        return $row === null ? throw OpsConsoleException::internal() : $this->map($row);
    }

    private function recordAudit(PlatformContext $context, OpsAuditEvent $event): void
    {
        $this->audit->platform(
            $context->operatorId, $context->accountId, $context->requestId,
            $event->eventType, $event->action, $event->metadata,
        );
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): MaintenanceWindow
    {
        return new MaintenanceWindow(
            (string) $row['maintenance_key'], (string) $row['state'], (string) $row['reason_key'],
            $this->iso((string) $row['starts_at']), $this->iso((string) $row['ends_at']), (int) $row['revision'],
        );
    }

    private function iso(string $value): string
    {
        return str_replace(' ', 'T', $value) . 'Z';
    }

    private function db(string $value): string
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
