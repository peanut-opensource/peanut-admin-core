<?php

declare(strict_types=1);

namespace PeanutAdmin\App\task;

use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use PeanutAdmin\TaskJob\Application\JobRecord;
use PeanutAdmin\TaskJob\Application\TaskJobException;
use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;
use think\Request;
use think\Response;

final class TaskHttpRuntime
{
    public static function list(Request $request): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $operation = TenantModuleRuntime::operation('listTasks', 'GET', '/api/v1/tasks', 'peanut.task-job', 'peanut.task-job.read');
        $external = TenantModuleRuntime::request($request, $operation, '/api/v1/tasks');
        $response = TenantModuleRuntime::host($pdo, $modules)->read($operation, $external, static function ($authorized, $query) use ($pdo) {
            try {
                self::emptyBody($query->body['payload'] ?? null);
                $raw = $query->body['query'] ?? null;
                if (!is_array($raw) || array_diff(array_keys($raw), ['status','page','page_size']) !== []) {
                    throw TaskJobException::invalid();
                }
                $result = (new TaskJobService(new PdoTaskJobRepository($pdo)))->list(
                    TenantModuleRuntime::authorizedContext($authorized, 'peanut.task-job', 'read'),
                    is_string($raw['status'] ?? null) ? $raw['status'] : 'queued',
                    TenantModuleRuntime::positiveInt($raw['page'] ?? '1', 10000),
                    TenantModuleRuntime::positiveInt($raw['page_size'] ?? '20', 100),
                );
                return new \PeanutAdmin\Kernel\Host\ExternalOperationResponse(200, ['data' => ['items' => array_map(static fn(JobRecord $job) => $job->toPublicArray(), $result['items'])],'page' => $result['page'],'page_size' => $result['page_size'],'total' => $result['total']]);
            } catch (TaskJobException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    public static function detail(Request $request, string $jobKey): Response
    {
        return self::readOne($request, $jobKey);
    }

    public static function cancel(Request $request, string $jobKey): Response
    {
        return self::mutate($request, $jobKey, 'cancelTask');
    }
    public static function retry(Request $request, string $jobKey): Response
    {
        return self::mutate($request, $jobKey, 'retryTask');
    }

    private static function readOne(Request $request, string $jobKey): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $path = '/api/v1/tasks/' . rawurlencode($jobKey);
        $operation = TenantModuleRuntime::operation('getTask', 'GET', '/api/v1/tasks/{job_key}', 'peanut.task-job', 'peanut.task-job.read');
        $external = TenantModuleRuntime::request($request, $operation, $path);
        $response = TenantModuleRuntime::host($pdo, $modules)->read($operation, $external, static function ($authorized, $query) use ($pdo, $jobKey) {
            try {
                self::noInput($query->body);
                $job = (new TaskJobService(new PdoTaskJobRepository($pdo)))->detail(TenantModuleRuntime::authorizedContext($authorized, 'peanut.task-job', 'read'), $jobKey);
                return new \PeanutAdmin\Kernel\Host\ExternalOperationResponse(200, ['data' => $job->toPublicArray()]);
            } catch (TaskJobException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    private static function mutate(Request $request, string $jobKey, string $operationId): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $suffix = $operationId === 'cancelTask' ? 'cancel' : 'retry';
        $path = '/api/v1/tasks/' . rawurlencode($jobKey) . '/' . $suffix;
        $operation = TenantModuleRuntime::operation($operationId, 'POST', '/api/v1/tasks/{job_key}/' . $suffix, 'peanut.task-job', 'peanut.task-job.manage', true);
        $external = TenantModuleRuntime::request($request, $operation, $path);
        $response = TenantModuleRuntime::host($pdo, $modules)->command($operation, $external, static function ($authorized, $command, PDO $transaction) use ($jobKey, $operationId) {
            try {
                self::noInput($command->body);
                $service = new TaskJobService(new PdoTaskJobRepository($transaction));
                $revision = TenantModuleRuntime::expectedRevision($command);
                if ($revision === null) {
                    throw TaskJobException::invalid();
                }$context = TenantModuleRuntime::authorizedContext($authorized, 'peanut.task-job', 'manage');
                $job = $operationId === 'cancelTask' ? $service->cancel($context, $jobKey, $revision) : $service->retry($context, $jobKey, $revision);
                return new ExternalOperationResult(200, ['data' => $job->toPublicArray()], 'tenant.task.managed', 'peanut.task-job.manage', ['state' => $job->status,'revision' => $job->revision], 'task', $job->jobKey);
            } catch (TaskJobException $e) {
                throw self::problem($e);
            }
        }, guard: TenantModuleRuntime::commandGuard('peanut.task-job'));
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    private static function emptyBody(mixed $body): void
    {
        if (!is_array($body) || $body !== []) {
            throw TaskJobException::invalid();
        }
    }
    /** @param array<string, mixed> $body */
    private static function noInput(array $body): void
    {
        self::emptyBody($body['payload'] ?? null);
        if (($body['query'] ?? null) !== []) {
            throw TaskJobException::invalid();
        }
    }
    private static function problem(TaskJobException $e): ApiException
    {
        return new ApiException($e->problemCode, $e->status, 'The task operation could not be completed.');
    }
}
