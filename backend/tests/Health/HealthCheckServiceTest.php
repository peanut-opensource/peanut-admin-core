<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Health;

use Closure;
use PeanutAdmin\App\command\HealthCheckService;
use PHPUnit\Framework\TestCase;

final class HealthCheckServiceTest extends TestCase
{
    public function testHealthyReportContainsDatabaseCacheAndApplicationChecks(): void
    {
        $service = new HealthCheckService(
            Closure::fromCallable(static fn(): bool => true),
            Closure::fromCallable(static fn(): bool => true),
            Closure::fromCallable(static fn(): bool => true),
        );

        $report = $service->check();

        self::assertSame('healthy', $report->status);
        self::assertSame(200, $report->httpStatus());
        self::assertSame(['app', 'cache', 'database'], array_keys($report->checks));
    }

    public function testCacheFailureIsDegradedButDatabaseFailureIsUnhealthy(): void
    {
        $degraded = (new HealthCheckService(
            Closure::fromCallable(static fn(): bool => true),
            Closure::fromCallable(static fn(): bool => false),
            Closure::fromCallable(static fn(): bool => true),
        ))->check();
        self::assertSame('degraded', $degraded->status);
        self::assertSame(200, $degraded->httpStatus());

        $unhealthy = (new HealthCheckService(
            Closure::fromCallable(static fn(): bool => false),
            Closure::fromCallable(static fn(): bool => true),
            Closure::fromCallable(static fn(): bool => true),
        ))->check();
        self::assertSame('unhealthy', $unhealthy->status);
        self::assertSame(503, $unhealthy->httpStatus());
    }
}
