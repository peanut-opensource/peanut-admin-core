<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\ops\OpsRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogQuery;
use think\Request;
use think\Response;

final class OpsConsoleController
{
    #[OpenApiHandlerContract] public function status(Request $r): Response
    {
        return $this->run($r, fn($pdo, $c) => ['data' => OpsRuntimeFactory::status($pdo)->read($c)->toPublicArray()]);
    }
    #[OpenApiHandlerContract] public function maintenance(Request $r): Response
    {
        return $this->run($r, fn($pdo, $c) => ['data' => OpsRuntimeFactory::maintenance($pdo)->current($c)?->toPublicArray()]);
    }
    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function scheduleMaintenance(Request $r): Response
    {
        return $this->run($r, function ($pdo, $c) use ($r) {
            $p = $this->body($r, ['reason_key','starts_at','ends_at']);
            $w = OpsRuntimeFactory::maintenance($pdo)->schedule($c, $this->string($p, 'reason_key'), $this->string($p, 'starts_at'), $this->string($p, 'ends_at'), $this->revision($r, true), $this->idempotency($r));
            return ['data' => $w->toPublicArray(),'status' => 201,'etag' => '"rev-' . $w->revision . '"'];
        });
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function closeMaintenance(Request $r, string $maintenanceKey): Response
    {
        return $this->run($r, function ($pdo, $c) use ($r, $maintenanceKey) {
            $w = OpsRuntimeFactory::maintenance($pdo)->close($c, $maintenanceKey, $this->revision($r), $this->idempotency($r));
            return ['data' => $w->toPublicArray(),'etag' => '"rev-' . $w->revision . '"'];
        });
    }
    #[OpenApiHandlerContract(successStatus: 202, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function backup(Request $r): Response
    {
        return $this->run($r, function ($pdo, $c) use ($r) {
            $p = $this->body($r, ['provider_key']);
            $t = OpsRuntimeFactory::tasks($pdo)->submitBackup($c, $this->string($p, 'provider_key'), $this->idempotency($r));
            return ['data' => $t->toPublicArray(),'status' => 202,'etag' => '"rev-' . $t->revision . '"'];
        });
    }
    #[OpenApiHandlerContract(successStatus: 202, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function restore(Request $r): Response
    {
        return $this->run($r, function ($pdo, $c) use ($r) {
            $p = $this->body($r, ['provider_key','backup_reference_key','target_key']);
            $t = OpsRuntimeFactory::tasks($pdo)->submitRestore($c, $this->string($p, 'provider_key'), $this->string($p, 'backup_reference_key'), $this->string($p, 'target_key'), $this->idempotency($r));
            return ['data' => $t->toPublicArray(),'status' => 202,'etag' => '"rev-' . $t->revision . '"'];
        });
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function task(Request $r, string $taskKey): Response
    {
        return $this->run($r, function ($pdo, $c) use ($taskKey) {
            $task = OpsRuntimeFactory::tasks($pdo)->task($c, $taskKey);
            return ['data' => $task->toPublicArray(),'etag' => '"rev-' . $task->revision . '"'];
        });
    }
    #[OpenApiHandlerContract] public function logs(Request $r): Response
    {
        return $this->run($r, function ($pdo, $c) use ($r) {
            $page = OpsRuntimeFactory::logs($pdo)->read($c, new RuntimeLogQuery((string) $r->get('source', 'platform.audit'), (string) $r->get('severity', 'info'), is_string($r->get('cursor')) ? $r->get('cursor') : null, (int) $r->get('page_size', 20)));
            return ['data' => $page->toPublicArray()];
        });
    }

    private function run(Request $r, callable $operation): Response
    {
        return MemberAdminRuntime::run($r, function () use ($r, $operation) {
            try {
                return $operation(MemberAdminRuntime::pdo(), $this->context($r));
            } catch (OpsConsoleException $e) {
                throw new AdminAccessException($e->problemCode, $e->status, 'The operations request could not be completed.');
            }
        });
    }
    private function context(Request $r): PlatformContext
    {
        $route = $r->route();
        $c = is_array($route) ? ($route['platform_context'] ?? null) : null;
        if (!$c instanceof PlatformContext) {
            throw new AdminAccessException('CONTEXT_PLATFORM_REQUIRED', 403, 'A platform context is required.');
        }return $c;
    }
    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function body(Request $r, array $keys): array
    {
        $p = MemberAdminRuntime::body($r);
        $actual = array_keys($p);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw OpsConsoleException::invalid();
        }return $p;
    }
    /** @param array<string,mixed> $p */
    private function string(array $p, string $key): string
    {
        $v = $p[$key] ?? null;
        if (!is_string($v)) {
            throw OpsConsoleException::invalid();
        }return $v;
    }
    private function idempotency(Request $r): string
    {
        $v = MemberAdminRuntime::header($r, 'idempotency-key');
        if (!is_string($v)) {
            throw OpsConsoleException::invalid();
        }return $v;
    }private function revision(Request $r, bool $allowZero = false): int
    {
        $v = MemberAdminRuntime::header($r, 'if-match');
        $pattern = $allowZero ? '/^"rev-(0|[1-9][0-9]*)"$/D' : '/^"rev-([1-9][0-9]*)"$/D';
        if (!is_string($v) || preg_match($pattern, $v, $m) !== 1) {
            throw OpsConsoleException::revisionConflict();
        }return (int) $m[1];
    }
}
