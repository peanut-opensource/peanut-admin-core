<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use InvalidArgumentException;
use PDO;
use PeanutAdmin\DataPermission\Exception\DataAuthorizationException;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Audit\GovernanceAuditFilter;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;
use PeanutAdmin\Kernel\Module\ModuleException;
use RuntimeException;
use think\Request;
use think\Response;
use Throwable;

final class MemberAdminRuntime
{
    private function __construct() {}

    public static function pdo(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_DATABASE') ?: 'peanut_admin',
            ),
            getenv('DB_USERNAME') ?: 'peanut_admin',
            getenv('DB_PASSWORD') ?: 'peanut_admin_dev',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public static function context(Request $request): TenantContext
    {
        $route = $request->route();
        $context = is_array($route) ? ($route['tenant_context'] ?? null) : null;
        if (!$context instanceof TenantContext) {
            throw new AdminAccessException('CONTEXT_TENANT_REQUIRED', 403, 'A tenant context is required.');
        }

        return $context;
    }

    /** @return array<string, mixed> */
    public static function body(Request $request): array
    {
        $body = $request->post();

        return is_array($body) ? $body : [];
    }

    public static function page(Request $request): PageRequest
    {
        return new PageRequest(
            (int) $request->get('page', 1),
            (int) $request->get('page_size', 20),
        );
    }

    public static function auditFilter(Request $request): GovernanceAuditFilter
    {
        $outcome = self::queryString($request, 'outcome');
        $parsedOutcome = $outcome === null ? null : AuditOutcome::tryFrom($outcome);
        if ($outcome !== null && $parsedOutcome === null) {
            throw AdminAccessException::invalid('AUDIT_FILTER_INVALID', 'The audit filter is invalid.');
        }
        try {
            return new GovernanceAuditFilter(
                self::queryString($request, 'event_type'),
                self::queryString($request, 'action'),
                $parsedOutcome,
                self::queryString($request, 'request_id'),
                self::queryString($request, 'target_type'),
                self::queryString($request, 'target_id'),
            );
        } catch (InvalidArgumentException) {
            throw AdminAccessException::invalid('AUDIT_FILTER_INVALID', 'The audit filter is invalid.');
        }
    }

    public static function header(Request $request, string $name): ?string
    {
        $value = $request->header($name);

        return is_string($value) ? $value : null;
    }

    private static function queryString(Request $request, string $name): ?string
    {
        $value = $request->get($name);
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw AdminAccessException::invalid('AUDIT_FILTER_INVALID', 'The audit filter is invalid.');
        }

        return $value;
    }

    /**
     * @param callable(): array{data: mixed, status?: int, etag?: string, location?: string, meta?: array<string, mixed>} $operation
     */
    public static function run(Request $request, callable $operation): Response
    {
        $requestId = self::requestId($request);
        try {
            $result = $operation();
            $payload = [
                'data' => $result['data'],
                'meta' => ['request_id' => $requestId, ...($result['meta'] ?? [])],
            ];
            $response = Response::create($payload, 'json', $result['status'] ?? 200);
            $headers = ['X-Request-Id' => $requestId, 'Cache-Control' => 'no-store'];
            if (isset($result['etag'])) {
                $headers['ETag'] = $result['etag'];
            }
            if (isset($result['location'])) {
                $headers['Location'] = $result['location'];
            }

            return $response->header($headers);
        } catch (AdminAccessException $exception) {
            return self::problem($exception, $requestId);
        } catch (DataAuthorizationException|ModuleException $exception) {
            throw $exception;
        } catch (Throwable) {
            return self::problem(
                new AdminAccessException('INTERNAL_ERROR', 500, 'The request could not be completed.'),
                $requestId,
            );
        }
    }

    public static function requestId(Request $request): string
    {
        $requestId = $request->header('x-request-id');
        if (is_string($requestId) && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $requestId) === 1) {
            return $requestId;
        }

        try {
            return 'req_' . bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not generate a request ID.', 0, $exception);
        }
    }

    private static function problem(AdminAccessException $exception, string $requestId): Response
    {
        $slug = strtolower(str_replace('_', '-', $exception->errorCode));

        return Response::create([
            'type' => '/docs/problems/' . $slug,
            'title' => $exception->httpStatus === 404 ? 'Resource not found' : 'Request rejected',
            'status' => $exception->httpStatus,
            'detail' => $exception->getMessage(),
            'instance' => 'urn:request:' . $requestId,
            'code' => $exception->errorCode,
            'request_id' => $requestId,
        ], 'json', $exception->httpStatus)->header([
            'Content-Type' => 'application/problem+json',
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'no-store',
        ]);
    }
}
