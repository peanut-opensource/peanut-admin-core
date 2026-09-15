<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use Throwable;
use think\facade\Cache;
use think\facade\Db;

/** Health checks use the same framework-managed database and cache as the application. */
final readonly class HealthCheckService
{
    public function check(): HealthReport
    {
        $checks = [
            'database' => $this->probe(static fn(): bool => Db::table('information_schema.tables')->limit(1)->count() >= 0, true),
            'cache' => $this->probe(static function (): bool {
                Cache::get('peanut-admin:health-probe');
                return true;
            }, false),
            'app' => $this->probe($this->applicationReady(...), true),
        ];
        ksort($checks);

        $status = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] !== 'up' && $check['critical']) {
                $status = 'unhealthy';
                break;
            }
            if ($check['status'] !== 'up') {
                $status = 'degraded';
            }
        }

        return new HealthReport($status, $checks);
    }

    /** @param callable(): bool $probe
     * @return array{status: string, critical: bool, latency_ms: float}
     */
    private function probe(callable $probe, bool $critical): array
    {
        $started = hrtime(true);
        try {
            $up = $probe() === true;
        } catch (Throwable) {
            $up = false;
        }

        return [
            'status' => $up ? 'up' : 'down',
            'critical' => $critical,
            'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        ];
    }

    private function applicationReady(): bool
    {
        $required = ['pa_account', 'pa_tenant', 'pa_module_installation'];
        $tableCount = (int)Db::table('information_schema.tables')
            ->where('table_schema', Db::raw('DATABASE()'))
            ->whereIn('table_name', $required)
            ->count();
        if ($tableCount !== count($required)) {
            return false;
        }

        return Db::name('module_installation')->where('status', '<>', 'active')->count() === 0
            && Db::name('module_migration')->where('status', '<>', 'applied')->count() === 0;
    }
}
