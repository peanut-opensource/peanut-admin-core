<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use Closure;
use PDO;
use Throwable;

final readonly class HealthCheckService
{
    public function __construct(
        private Closure $databaseProbe,
        private Closure $cacheProbe,
        private Closure $applicationProbe,
    ) {}

    public static function fromEnvironment(): self
    {
        $pdo = static function (): PDO {
            static $connection;
            if (!$connection instanceof PDO) {
                $connection = new PDO(
                    sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        getenv('DB_HOST') ?: '127.0.0.1',
                        (int) (getenv('DB_PORT') ?: 3306),
                        getenv('DB_DATABASE') ?: 'peanut_admin',
                    ),
                    getenv('DB_USERNAME') ?: 'peanut_admin',
                    getenv('DB_PASSWORD') ?: 'peanut_admin_dev',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
            }

            return $connection;
        };

        return new self(
            Closure::fromCallable(static fn(): bool => (int) self::column($pdo(), 'SELECT 1') === 1),
            Closure::fromCallable(static fn(): bool => self::pingCache()),
            Closure::fromCallable(static function () use ($pdo): bool {
                $required = ['pa_account', 'pa_tenant', 'pa_module_installation'];
                $statement = $pdo()->prepare(<<<'SQL'
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name IN (?, ?, ?)
SQL);
                $statement->execute($required);
                if ((int) $statement->fetchColumn() !== count($required)) {
                    return false;
                }
                $failedModules = (int) self::column(
                    $pdo(),
                    "SELECT COUNT(*) FROM pa_module_installation WHERE status <> 'active'",
                );
                $failedMigrations = (int) self::column(
                    $pdo(),
                    "SELECT COUNT(*) FROM pa_module_migration WHERE status <> 'applied'",
                );

                return $failedModules === 0 && $failedMigrations === 0;
            }),
        );
    }

    public function check(): HealthReport
    {
        $checks = [
            'database' => $this->probe($this->databaseProbe, true),
            'cache' => $this->probe($this->cacheProbe, false),
            'app' => $this->probe($this->applicationProbe, true),
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

    /** @return array{status: string, critical: bool, latency_ms: float} */
    private function probe(Closure $probe, bool $critical): array
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

    private static function pingCache(): bool
    {
        $host = getenv('CACHE_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('CACHE_PORT') ?: 6379);
        $timeout = (float) (getenv('HEALTH_CACHE_TIMEOUT') ?: 0.5);
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, $timeout);
        if (!is_resource($socket)) {
            return false;
        }

        try {
            stream_set_timeout($socket, 0, (int) ($timeout * 1_000_000));
            fwrite($socket, "*1\r\n$4\r\nPING\r\n");

            return fgets($socket) === "+PONG\r\n";
        } finally {
            fclose($socket);
        }
    }

    private static function column(PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return false;
        }

        return $statement->fetchColumn();
    }
}
