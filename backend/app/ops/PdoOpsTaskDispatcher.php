<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Task\OpsTask;
use PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;

final readonly class PdoOpsTaskDispatcher implements OpsTaskDispatcher
{
    public function __construct(private PDO $pdo) {}
    public function dispatch(PlatformContext $context, OpsTaskSubmission $submission): OpsTask
    {
        return (new PdoTransactionManager($this->pdo))->run(function () use ($context, $submission) {
            $existing = $this->one('SELECT * FROM pa_ops_task WHERE submitted_by_operator_id=:operator AND idempotency_digest=:digest FOR UPDATE', ['operator' => $context->operatorId,'digest' => $submission->idempotencyDigest]);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['request_digest'], $submission->requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }return $this->map($existing);
            } $active = $this->one("SELECT id FROM pa_ops_task WHERE concurrency_key=:key AND status IN ('queued','running') LIMIT 1 FOR UPDATE", ['key' => $submission->concurrencyKey]);
            if ($active !== null) {
                throw OpsConsoleException::operationInProgress();
            }$key = 'job_' . bin2hex(random_bytes(16));
            $statement = $this->pdo->prepare('INSERT INTO pa_ops_task (task_key,task_type,handler_key,payload_json,max_attempts,idempotency_digest,request_digest,concurrency_key,submitted_by_operator_id,available_at,created_at,updated_at) VALUES (:task_key,:task_type,:handler_key,:payload,:attempts,:idempotency,:request,:concurrency,:operator,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))');
            $statement->execute(['task_key' => $key,'task_type' => $submission->taskType,'handler_key' => $submission->handlerKey,'payload' => json_encode($submission->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),'attempts' => $submission->maximumAttempts,'idempotency' => $submission->idempotencyDigest,'request' => $submission->requestDigest,'concurrency' => $submission->concurrencyKey,'operator' => $context->operatorId]);
            (new PdoAuditRepository($this->pdo))->appendPlatform($submission->audit->eventType, $submission->audit->action, $context->requestId, $context->operatorId, $context->accountId, $submission->audit->metadata);
            return $this->find($context, $key);
        });
    }
    public function find(PlatformContext $context, string $taskKey): OpsTask
    {
        $row = $this->one('SELECT * FROM pa_ops_task WHERE task_key=:key', ['key' => $taskKey]);
        if ($row === null) {
            throw OpsConsoleException::taskNotFound();
        }return $this->map($row);
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
    private function map(array $r): OpsTask
    {
        return new OpsTask((string) $r['task_key'], (string) $r['task_type'], (string) $r['status'], (int) $r['attempt_count'], (int) $r['max_attempts'], (int) $r['revision'], $r['last_error_code'] === null ? null : (string) $r['last_error_code'], $this->instant((string) $r['available_at']), $this->instant((string) $r['created_at']), $this->instant((string) $r['updated_at']), $r['completed_at'] === null ? null : $this->instant((string) $r['completed_at']));
    }
    private function instant(string $v): string
    {
        return str_replace(' ', 'T', $v) . 'Z';
    }
}
