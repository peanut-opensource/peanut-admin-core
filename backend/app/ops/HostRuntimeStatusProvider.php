<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\App\command\HealthCheckService;
use PeanutAdmin\App\upgrade\RepositoryInspector;
use PeanutAdmin\App\upgrade\UpgradeStatusService;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Status\OpsStatusSnapshot;
use PeanutAdmin\OpsConsole\Status\RuntimeStatusProvider;

final readonly class HostRuntimeStatusProvider implements RuntimeStatusProvider
{
    public function __construct(private PDO $pdo, private string $root) {}
    public function snapshot(PlatformContext $context): OpsStatusSnapshot
    {
        $health = HealthCheckService::fromEnvironment()->check();
        $checks = [];
        foreach ($health->checks as $key => $check) {
            $checks[] = ['key' => $key,'status' => $check['status'],'critical' => $check['critical'],'latency_ms' => $check['latency_ms']];
        }
        $repository = (new RepositoryInspector())->inspect($this->root);
        $statement = $this->pdo->query("SELECT module_key,migration_key,checksum,status FROM pa_module_migration ORDER BY module_key,migration_key");
        if ($statement === false) {
            throw new \RuntimeException('Unable to read migration status.');
        }$migrations = $statement->fetchAll(PDO::FETCH_ASSOC);
        $applied = count(array_filter($migrations, static fn(array $r): bool => $r['status'] === 'applied'));
        $digest = hash('sha256', json_encode($migrations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $upgrade = UpgradeStatusService::fromEnvironment()->status();
        $target = is_array($upgrade['target'] ?? null) ? $upgrade['target'] : [];
        $preflight = is_array($upgrade['preflight'] ?? null) ? $upgrade['preflight'] : [];
        $backup = is_array($upgrade['backup'] ?? null) ? $upgrade['backup'] : [];
        return new OpsStatusSnapshot($health->status, $checks, $repository->commit, $repository->tree, is_string($target['release_id'] ?? null) ? $target['release_id'] : null, (new \DateTimeImmutable('@' . filemtime($this->root . '/composer.lock')))->format('Y-m-d\TH:i:s.v\Z'), $applied, count($migrations), count($migrations) - $applied, $digest, count($migrations) !== $applied, (string) ($upgrade['state'] ?? 'blocked'), (string) ($preflight['code'] ?? 'UPGRADE_STATUS_UNAVAILABLE'), null, is_string($target['commit'] ?? null) ? $target['commit'] : null, (bool) ($preflight['repository_clean'] ?? false), (bool) ($backup['valid'] ?? false), (bool) ($backup['source_identity_matches'] ?? false));
    }
}
