<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use PeanutAdmin\NotificationSms\Application\NotificationException;
use PeanutAdmin\NotificationSms\Application\NotificationMessage;
use think\Request;
use think\Response;

final class NotificationHttpRuntime
{
    public static function inbox(Request $request): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $op = TenantModuleRuntime::operation('listNotifications', 'GET', '/api/v1/notifications', 'peanut.notification-sms', 'peanut.notification-sms.read');
        $external = TenantModuleRuntime::request($request, $op, '/api/v1/notifications');
        $response = TenantModuleRuntime::host($pdo, $modules)->read($op, $external, static function ($authorized, $query) use ($pdo) {
            try {
                self::empty($query->body['payload'] ?? null);
                $raw = $query->body['query'] ?? null;
                if (!is_array($raw) || array_diff(array_keys($raw), ['status','page','page_size']) !== []) {
                    throw NotificationException::invalid();
                }$result = NotificationRuntimeFactory::service($pdo)->inbox(TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'read'), is_string($raw['status'] ?? null) ? $raw['status'] : 'all', TenantModuleRuntime::positiveInt($raw['page'] ?? '1', 10000), TenantModuleRuntime::positiveInt($raw['page_size'] ?? '20', 100));
                return new ExternalOperationResponse(200, ['data' => ['items' => array_map(static fn(NotificationMessage $message) => $message->toArray(), $result['items'])],'page' => $result['page'],'page_size' => $result['page_size'],'total' => $result['total']]);
            } catch (NotificationException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    public static function markRead(Request $request, string $messageKey): Response
    {
        return self::command($request, 'markNotificationRead', '/api/v1/notifications/{message_key}/read', '/api/v1/notifications/' . rawurlencode($messageKey) . '/read', static function ($authorized, $command, PDO $pdo) use ($messageKey) {
            self::noInput($command->body);
            $revision = TenantModuleRuntime::expectedRevision($command);
            if ($revision === null) {
                throw NotificationException::invalid();
            }$message = NotificationRuntimeFactory::service($pdo)->markRead(TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'read'), $messageKey, $revision);
            return new ExternalOperationResult(200, ['data' => $message->toArray()], 'tenant.notification.read', 'peanut.notification-sms.read', ['revision' => $message->revision], 'notification', $message->messageKey);
        }, 'peanut.notification-sms.read');
    }

    public static function bulk(Request $request): Response
    {
        return self::command($request, 'bulkUpdateNotifications', '/api/v1/notifications/bulk', '/api/v1/notifications/bulk', static function ($authorized, $command, PDO $pdo) {
            $payload = self::payload($command->body, ['message_keys','action']);
            $changed = NotificationRuntimeFactory::service($pdo)->bulk(TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'read'), self::strings($payload, 'message_keys'), is_string($payload['action']) ? $payload['action'] : '');
            return new ExternalOperationResult(200, ['data' => ['changed' => $changed]], 'tenant.notification.bulk.updated', 'peanut.notification-sms.read', ['changed' => $changed]);
        }, 'peanut.notification-sms.read');
    }

    public static function putTemplate(Request $request, string $templateKey): Response
    {
        return self::command($request, 'putNotificationTemplate', '/api/v1/notification-templates/{template_key}', '/api/v1/notification-templates/' . rawurlencode($templateKey), static function ($authorized, $command, PDO $pdo) use ($templateKey) {
            $p = self::payload($command->body, ['name','subject_template','body_template','channels','variables']);
            $template = NotificationRuntimeFactory::service($pdo)->putTemplate(TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'manage'), $templateKey, self::string($p, 'name'), self::string($p, 'subject_template'), self::string($p, 'body_template'), self::strings($p, 'channels'), self::strings($p, 'variables'), TenantModuleRuntime::expectedRevision($command, true));
            return new ExternalOperationResult(200, ['data' => $template], 'tenant.notification.template.saved', 'peanut.notification-sms.manage', ['template_key' => $templateKey,'revision' => (int) $template['revision']], 'notification-template', $templateKey);
        });
    }

    public static function publish(Request $request): Response
    {
        return self::command($request, 'createNotification', '/api/v1/notifications', '/api/v1/notifications', static function ($authorized, $command, PDO $pdo) {
            $p = self::payload($command->body, ['template_key','recipients','file_keys']);
            $context = TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'manage');
            $result = NotificationRuntimeFactory::service($pdo)->publish($context, self::string($p, 'template_key'), self::recipients($p), self::strings($p, 'file_keys'));
            $jobs = [];
            $dispatcher = NotificationRuntimeFactory::dispatcher($pdo);
            foreach ($result['outbox'] as $outbox) {
                $jobs[] = $dispatcher->dispatch($context, $outbox->outboxKey)->jobKey;
            }return new ExternalOperationResult(201, ['data' => ['messages' => array_map(static fn(NotificationMessage $m) => $m->toArray(), $result['messages']),'job_keys' => $jobs]], 'tenant.notification.published', 'peanut.notification-sms.manage', ['message_count' => count($result['messages']),'job_count' => count($jobs)]);
        });
    }

    public static function dispatch(Request $request, string $outboxKey): Response
    {
        return self::command($request, 'dispatchNotificationOutbox', '/api/v1/notification-outbox/{outbox_key}/dispatch', '/api/v1/notification-outbox/' . rawurlencode($outboxKey) . '/dispatch', static function ($authorized, $command, PDO $pdo) use ($outboxKey) {
            self::noInput($command->body);
            $job = NotificationRuntimeFactory::dispatcher($pdo)->dispatch(TenantModuleRuntime::authorizedContext($authorized, 'peanut.notification-sms', 'manage'), $outboxKey);
            return new ExternalOperationResult(202, ['data' => $job->toPublicArray()], 'tenant.notification.dispatch.queued', 'peanut.notification-sms.manage', ['task_type' => $job->taskType], 'task', $job->jobKey);
        });
    }

    private static function command(Request $request, string $id, string $template, string $path, callable $handler, string $permission = 'peanut.notification-sms.manage'): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $op = TenantModuleRuntime::operation($id, $id === 'putNotificationTemplate' ? 'PUT' : 'POST', $template, 'peanut.notification-sms', $permission, true);
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = TenantModuleRuntime::host($pdo, $modules)->command($op, $external, static function ($authorized, $command, PDO $transaction) use ($handler) {
            try {
                return $handler($authorized, $command, $transaction);
            } catch (NotificationException $e) {
                throw self::problem($e);
            }
        }, guard: TenantModuleRuntime::commandGuard('peanut.notification-sms'));
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    private static function empty(mixed $value): void
    {
        if (!is_array($value) || $value !== []) {
            throw NotificationException::invalid();
        }
    }
    /** @param array<string,mixed> $body */
    private static function noInput(array $body): void
    {
        self::empty($body['payload'] ?? null);
        if (($body['query'] ?? null) !== []) {
            throw NotificationException::invalid();
        }
    }
    /**
     * @param array<string, mixed> $body
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private static function payload(array $body, array $keys): array
    {
        if (($body['query'] ?? null) !== []) {
            throw NotificationException::invalid();
        }$p = $body['payload'] ?? null;
        if (!is_array($p)) {
            throw NotificationException::invalid();
        }$actual = array_keys($p);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw NotificationException::invalid();
        }return $p;
    }
    /** @param array<string,mixed> $p */
    private static function string(array $p, string $key): string
    {
        $v = $p[$key] ?? null;
        if (!is_string($v)) {
            throw NotificationException::invalid();
        }return $v;
    }
    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private static function strings(array $p, string $key): array
    {
        $v = $p[$key] ?? null;
        if (!is_array($v) || !array_is_list($v) || array_filter($v, static fn($i) => !is_string($i)) !== []) {
            throw NotificationException::invalid();
        }return $v;
    }
    /**
     * @param array<string, mixed> $payload
     * @return list<array{member_id: int, variables: array<string, scalar|null>}>
     */
    private static function recipients(array $payload): array
    {
        $value = $payload['recipients'] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw NotificationException::invalid();
        }
        $result = [];
        foreach ($value as $recipient) {
            if (!is_array($recipient)) {
                throw NotificationException::invalid();
            }
            $keys = array_keys($recipient);
            sort($keys, SORT_STRING);
            if ($keys !== ['member_id','variables'] || !is_string($recipient['member_id']) || preg_match('/^[1-9][0-9]{0,18}$/D', $recipient['member_id']) !== 1 || !is_array($recipient['variables'])) {
                throw NotificationException::invalid();
            }
            $memberId = filter_var($recipient['member_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($memberId)) {
                throw NotificationException::invalid();
            }
            foreach ($recipient['variables'] as $key => $item) {
                if (!is_string($key) || $key === '' || (!is_scalar($item) && $item !== null)) {
                    throw NotificationException::invalid();
                }
            }
            $result[] = ['member_id' => $memberId,'variables' => $recipient['variables']];
        }
        return $result;
    }
    private static function problem(NotificationException $e): ApiException
    {
        $status = match ($e->problemCode) {
            'NOTIFICATION_NOT_FOUND' => 404,'NOTIFICATION_STATE_CONFLICT' => 409,'NOTIFICATION_PERMISSION_DENIED' => 403,default => 422,
        };
        return new ApiException($e->problemCode, $status, 'The notification operation could not be completed.');
    }
}
