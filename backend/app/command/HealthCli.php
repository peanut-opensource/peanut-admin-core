<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

final class HealthCli
{
    private function __construct() {}

    public static function run(): int
    {
        $report = HealthCheckService::fromEnvironment()->check();
        fwrite(STDOUT, json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

        return $report->status === 'healthy' ? 0 : 1;
    }
}
