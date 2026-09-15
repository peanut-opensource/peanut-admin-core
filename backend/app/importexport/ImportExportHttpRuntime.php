<?php

declare(strict_types=1);

namespace PeanutAdmin\App\importexport;

use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\Application\OperationRecord;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use think\Request;
use think\Response;

final class ImportExportHttpRuntime
{
    public function __construct(
        private readonly \PeanutAdmin\ImportExport\Application\ImportExportService $service,
        private readonly TenantModuleRuntime $runtime,
    ) {}

    public function index(Request $request): Response
    {
        return $this->read($request, 'listImportExportOperations', '/api/v1/import-export/operations', '/api/v1/import-export/operations', function ($context, array $query) {
            if (array_diff(array_keys($query), ['status','page','page_size']) !== []) {
                throw ImportExportException::invalid();
            }$result = $this->service->list($context, is_string($query['status'] ?? null) ? $query['status'] : 'queued', TenantModuleRuntime::positiveInt($query['page'] ?? '1', 10000), TenantModuleRuntime::positiveInt($query['page_size'] ?? '20', 100));
            return ['data' => ['items' => array_map(static fn(OperationRecord $r) => $r->toPublicArray(), $result['items'])],'page' => $result['page'],'page_size' => $result['page_size'],'total' => $result['total']];
        });
    }
    public function show(Request $request, string $key): Response
    {
        return $this->read($request, 'getImportExportOperation', '/api/v1/import-export/operations/{operation_key}', '/api/v1/import-export/operations/' . rawurlencode($key), fn($context, array $query) => $query === [] ? ['data' => $this->service->detail($context, $key)->toPublicArray()] : throw ImportExportException::invalid());
    }
    public function submitImport(Request $request): Response
    {
        return $this->command($request, 'submitImport', '/api/v1/import-export/imports', '/api/v1/import-export/imports', 'create', function ($context, array $p, string $idempotency) {
            self::keys($p, ['provider_key','file_key','mapping']);
            if (!is_array($p['mapping'])) {
                throw ImportExportException::invalid();
            }foreach ($p['mapping'] as $k => $v) {
                if (!is_string($k) || !is_string($v)) {
                    throw ImportExportException::invalid();
                }
            }return $this->service->submitImport($context, self::string($p, 'provider_key'), self::string($p, 'file_key'), $p['mapping'], $idempotency);
        }, 201);
    }
    public function submitExport(Request $request): Response
    {
        return $this->command($request, 'submitExport', '/api/v1/import-export/exports', '/api/v1/import-export/exports', 'create', function ($context, array $p, string $idempotency) {
            self::keys($p, ['provider_key']);
            return $this->service->submitExport($context, self::string($p, 'provider_key'), $idempotency);
        }, 201);
    }
    public function cancel(Request $request, string $key): Response
    {
        return $this->command($request, 'cancelImportExportOperation', '/api/v1/import-export/operations/{operation_key}/cancel', '/api/v1/import-export/operations/' . rawurlencode($key) . '/cancel', 'cancel', function ($context, array $p, string $idempotency, int $revision) use ($key) {
            self::keys($p, []);
            return $this->service->cancel($context, $key, $revision);
        });
    }

    private function read(Request $request, string $id, string $template, string $path, callable $handler): Response
    {
        $op = TenantModuleRuntime::operation($id, 'GET', $template, 'peanut.import-export', 'peanut.import-export.read');
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = $this->runtime->host()->read($op, $external, static function ($authorized, $query) use ($handler) {
            try {
                self::keys($query->body['payload'] ?? null, []);
                $raw = $query->body['query'] ?? null;
                if (!is_array($raw)) {
                    throw ImportExportException::invalid();
                }$body = $handler(TenantModuleRuntime::authorizedContext($authorized, 'peanut.import-export', 'read'), $raw);
                return new ExternalOperationResponse(200, $body);
            } catch (ImportExportException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }
    private function command(Request $request, string $id, string $template, string $path, string $operation, callable $handler, int $status = 200): Response
    {
        $op = TenantModuleRuntime::operation($id, 'POST', $template, 'peanut.import-export', 'peanut.import-export.' . $operation, true, true);
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = $this->runtime->host()->command($op, $external, static function ($authorized, $command) use ($handler, $operation, $status) {
            try {
                $payload = $command->body['payload'] ?? null;
                if (!is_array($payload) || ($command->body['query'] ?? null) !== [] || !is_string($command->idempotencyKey)) {
                    throw ImportExportException::invalid();
                }$record = $handler(TenantModuleRuntime::authorizedContext($authorized, 'peanut.import-export', $operation), $payload, $command->idempotencyKey, TenantModuleRuntime::expectedRevision($command, true) ?? 1);
                return new ExternalOperationResult($status, ['data' => $record->toPublicArray()], 'tenant.import-export.changed', 'peanut.import-export.' . $operation, ['direction' => $record->direction,'revision' => $record->revision], 'import_export_operation', $record->operationKey);
            } catch (ImportExportException $e) {
                throw self::problem($e);
            }
        }, guard: $this->runtime->commandGuard('peanut.import-export'));
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    /** @param list<string> $expected */
    private static function keys(mixed $p, array $expected): void
    {
        if (!is_array($p)) {
            throw ImportExportException::invalid();
        }$actual = array_keys($p);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw ImportExportException::invalid();
        }
    }
    /** @param array<string,mixed> $p */
    private static function string(array $p, string $key): string
    {
        $v = $p[$key] ?? null;
        if (!is_string($v)) {
            throw ImportExportException::invalid();
        }return $v;
    }
    private static function problem(ImportExportException $e): ApiException
    {
        $status = match ($e->problemCode) {
            'IMPORT_EXPORT_PERMISSION_DENIED' => 403,'IMPORT_EXPORT_NOT_FOUND' => 404,'IMPORT_EXPORT_IDEMPOTENCY_CONFLICT','IMPORT_EXPORT_STATE_CONFLICT' => 409,'IMPORT_EXPORT_INTERNAL_ERROR' => 500,default => 422,
        };
        return new ApiException($e->problemCode, $status, 'The import/export operation could not be completed.');
    }
}
