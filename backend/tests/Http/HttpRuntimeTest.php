<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Http;

use PHPUnit\Framework\TestCase;
use think\App;
use think\Request;
use think\Response;

final class HttpRuntimeTest extends TestCase
{
    public function testHealthRouteRunsThroughTheRealThinkPhpStack(): void
    {
        $response = $this->request('GET', '/api/v1/health');

        self::assertContains($response->getCode(), [200, 503]);
        self::assertIsArray($response->getData());
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertMatchesRegularExpression('/^req_[a-f0-9]{32}$/', $response->getHeader('X-Request-Id'));
    }

    public function testProtectedOpenApiRouteRunsTheGuardAndProblemMiddleware(): void
    {
        $response = $this->request('GET', '/api/v1/members');

        self::assertSame(401, $response->getCode());
        self::assertSame('application/problem+json', $response->getHeader('Content-Type'));
        self::assertSame('AUTH_TOKEN_INVALID', $response->getData()['code'] ?? null);
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
    }

    public function testPublicAuthRouteUsesTheHttpAdapterAndValidationContract(): void
    {
        $response = $this->request('POST', '/api/v1/auth/login', []);

        self::assertSame(422, $response->getCode());
        self::assertSame('VALIDATION_FAILED', $response->getData()['code'] ?? null);
        self::assertSame('/body/email', $response->getData()['errors'][0]['pointer'] ?? null);
    }

    public function testUnknownRouteFailsAsProblemDetails(): void
    {
        $response = $this->request('GET', '/api/v1/not-a-route');

        self::assertSame(404, $response->getCode());
        self::assertSame('ROUTE_NOT_FOUND', $response->getData()['code'] ?? null);
        self::assertSame('application/problem+json', $response->getHeader('Content-Type'));
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $url, ?array $body = null): Response
    {
        $outputLevel = ob_get_level();
        $request = (new Request())
            ->setMethod($method)
            ->setUrl($url)
            ->withServer([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $url,
                'HTTP_HOST' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->withHeader([
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ]);
        if ($body !== null) {
            $request->withInput(json_encode($body, JSON_THROW_ON_ERROR));
        }
        $app = new App(dirname(__DIR__, 2));
        $http = $app->http;

        try {
            $response = $http->run($request);
            $http->end($response);

            return $response;
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
