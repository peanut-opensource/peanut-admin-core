<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Task\OpsTask;
use PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;
use think\facade\Db;

final readonly class ThinkPhpOpsTaskDispatcher implements OpsTaskDispatcher
{
    public function __construct(private AuditService $audit) {}

    public function dispatch(PlatformContext $context, OpsTaskSubmission $submission): OpsTask
    {
        return Db::transaction(function () use ($context, $submission): OpsTask {
            $existing = Db::name('ops_task')->where('submitted_by_operator_id', $context->operatorId)
                ->where('idempotency_digest', $submission->idempotencyDigest)->lock(true)->find();
            if ($existing !== null) {
                if (!hash_equals((string) $existing['request_digest'], $submission->requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }

                return $this->map($existing);
            }
            if (Db::name('ops_task')->where('concurrency_key', $submission->concurrencyKey)
                ->whereIn('status', ['queued', 'running'])->lock(true)->value('id') !== null) {
                throw OpsConsoleException::operationInProgress();
            }
            $key = 'job_' . bin2hex(random_bytes(16));
            $now = Db::raw('UTC_TIMESTAMP(3)');
            Db::name('ops_task')->insert([
                'task_key' => $key, 'task_type' => $submission->taskType, 'handler_key' => $submission->handlerKey,
                'payload_json' => json_encode($submission->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'max_attempts' => $submission->maximumAttempts, 'idempotency_digest' => $submission->idempotencyDigest,
                'request_digest' => $submission->requestDigest, 'concurrency_key' => $submission->concurrencyKey,
                'submitted_by_operator_id' => $context->operatorId, 'available_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit->platform(
                $context->operatorId, $context->accountId, $context->requestId,
                $submission->audit->eventType, $submission->audit->action, $submission->audit->metadata,
            );

            return $this->find($context, $key);
        });
    }

    public function find(PlatformContext $context, string $taskKey): OpsTask
    {
        $row = Db::name('ops_task')->where('task_key', $taskKey)->find();
        if ($row === null) {
            throw OpsConsoleException::taskNotFound();
        }

        return $this->map($row);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): OpsTask
    {
        return new OpsTask(
            (string) $row['task_key'], (string) $row['task_type'], (string) $row['status'],
            (int) $row['attempt_count'], (int) $row['max_attempts'], (int) $row['revision'],
            $row['last_error_code'] === null ? null : (string) $row['last_error_code'],
            $this->instant((string) $row['available_at']), $this->instant((string) $row['created_at']),
            $this->instant((string) $row['updated_at']),
            $row['completed_at'] === null ? null : $this->instant((string) $row['completed_at']),
        );
    }

    private function instant(string $value): string
    {
        return str_replace(' ', 'T', $value) . 'Z';
    }
}
