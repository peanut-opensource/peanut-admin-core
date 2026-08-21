<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api;

use PeanutAdmin\App\middleware\RequestIdMiddleware;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Http\TenantAuthResponse;
use think\Request;
use think\Response;

final class AuthHttpRuntime
{
    private function __construct() {}

    /** @return array<string, mixed> */
    public static function body(Request $request): array
    {
        $body = $request->post();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed> $body */
    public static function requiredString(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw self::validationError($field, 'A non-empty string is required.');
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    public static function optionalString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw self::validationError($field, 'The value must be a string or null.');
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    public static function positiveInteger(array $body, string $field): int
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw self::validationError($field, 'A positive decimal string is required.');
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($integer)) {
            throw self::validationError($field, 'The identifier is outside the supported range.');
        }

        return $integer;
    }

    public static function bearerToken(Request $request): string
    {
        $authorization = $request->header('authorization');
        if (!is_string($authorization)
            || preg_match('/^Bearer ([^\s]+)$/iD', $authorization, $matches) !== 1) {
            throw new ApiException('AUTH_TOKEN_INVALID', 401, 'A valid bearer token is required.');
        }

        return $matches[1];
    }

    public static function requiredCookie(Request $request, string $name): string
    {
        $value = $request->cookie($name);
        if (!is_string($value) || $value === '') {
            throw new ApiException('AUTH_TOKEN_INVALID', 401, 'A valid refresh token is required.');
        }

        return $value;
    }

    public static function requestId(Request $request): string
    {
        return RequestIdMiddleware::current($request);
    }

    public static function ipAddress(Request $request): string
    {
        return $request->ip();
    }

    public static function userAgent(Request $request): ?string
    {
        $value = $request->header('user-agent');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function trustedOrigin(Request $request): bool
    {
        $fetchSite = $request->header('sec-fetch-site');
        if (is_string($fetchSite) && strtolower($fetchSite) === 'cross-site') {
            return false;
        }
        $origin = $request->header('origin');
        if (!is_string($origin) || $origin === '') {
            return true;
        }
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        if (!is_string($originHost)) {
            return false;
        }
        $originAuthority = strtolower($originHost) . ($originPort === null ? '' : ':' . $originPort);

        return hash_equals(strtolower($request->host(false)), $originAuthority);
    }

    public static function tenantResponse(TenantAuthResponse $result): Response
    {
        return self::response($result->status, $result->body, $result->headers);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    public static function response(int $status, ?array $body, array $headers = []): Response
    {
        $response = $body === null
            ? Response::create('', 'html', $status)
            : Response::create($body, 'json', $status);

        return $response->header(['Cache-Control' => 'no-store', ...$headers]);
    }

    private static function validationError(string $field, string $message): ApiException
    {
        return new ApiException('VALIDATION_FAILED', 422, 'One or more fields are invalid.', [[
            'pointer' => '/body/' . $field,
            'code' => strtoupper($field) . '_INVALID',
            'message' => $message,
        ]]);
    }
}
