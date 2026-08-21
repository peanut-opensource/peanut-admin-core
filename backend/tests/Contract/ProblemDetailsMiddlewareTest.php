<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use PeanutAdmin\App\middleware\ProblemDetailsMiddleware;
use PeanutAdmin\App\middleware\RequestIdMiddleware;
use PeanutAdmin\Kernel\Api\ApiException;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

final class ProblemDetailsMiddlewareTest extends TestCase
{
    public function testRequestIdAndProblemContentTypeSurviveTheMiddlewareChain(): void
    {
        $request = new Request();
        $request->withHeader(['x-request-id' => 'req_CONTRACT_001']);
        $problem = new ProblemDetailsMiddleware();
        $requestId = new RequestIdMiddleware();

        $response = $requestId->handle(
            $request,
            static fn(Request $request): Response => $problem->handle(
                $request,
                static function (): never {
                    throw new ApiException('VALIDATION_FAILED', 422, 'One or more fields are invalid.', [[
                        'pointer' => '/query/page_size',
                        'code' => 'PAGE_SIZE_INVALID',
                        'message' => 'Page size is invalid.',
                    ]]);
                },
            ),
        );

        self::assertSame(422, $response->getCode());
        self::assertSame('application/problem+json', $response->getHeader('Content-Type'));
        self::assertSame('req_CONTRACT_001', $response->getHeader('X-Request-Id'));
        self::assertSame('/query/page_size', $response->getData()['errors'][0]['pointer']);
    }
}
