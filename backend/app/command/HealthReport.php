<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

final readonly class HealthReport
{
    /** @param array<string, array{status: string, critical: bool, latency_ms: float}> $checks */
    public function __construct(
        public string $status,
        public array $checks,
    ) {}

    public function httpStatus(): int
    {
        return $this->status === 'unhealthy' ? 503 : 200;
    }

    /** @return array{status: string, checks: array<string, array{status: string, critical: bool, latency_ms: float}>} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'checks' => $this->checks];
    }
}
