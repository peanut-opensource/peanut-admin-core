<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Upgrade;

use PeanutAdmin\App\upgrade\UpgradeStatusService;
use PHPUnit\Framework\TestCase;

final class UpgradeStatusServiceTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
    }

    public function testUnconfiguredStatusIsReadOnlyAndProvidesOnlyOperatorCliHandoff(): void
    {
        $root = dirname(__DIR__, 3);
        $status = (new UpgradeStatusService($root, null, null, 'production'))->status();

        self::assertSame('configuration_required', $status['state']);
        self::assertSame('UPGRADE_RELEASE_MANIFEST_REQUIRED', $status['preflight']['code']);
        self::assertFalse($status['execution']['remote_execution']);
        self::assertSame('operator_cli', $status['execution']['mode']);
        self::assertStringNotContainsString($root, json_encode($status, JSON_THROW_ON_ERROR));
    }

    public function testMissingEnvironmentFailsClosedWithoutGuessingProduction(): void
    {
        $root = dirname(__DIR__, 3);
        $status = (new UpgradeStatusService($root, null, null, null))->status();

        self::assertSame('configuration_required', $status['state']);
        self::assertSame('UPGRADE_ENVIRONMENT_REQUIRED', $status['preflight']['code']);
        self::assertFalse($status['preflight']['ready']);
    }

    public function testInvalidConfiguredBackupKeepsItsStableFailureCode(): void
    {
        $root = dirname(__DIR__, 3);
        $release = $this->temporaryJson([
            'schema_version' => 1,
            'release_id' => 'status-fixture',
            'source' => ['commit' => str_repeat('a', 40), 'tree' => str_repeat('b', 40)],
            'target' => ['commit' => str_repeat('c', 40), 'tree' => str_repeat('d', 40)],
            'migrations' => ['source' => [], 'target' => []],
        ]);
        $backup = $this->temporaryJson(['schema_version' => 1]);
        $status = (new UpgradeStatusService($root, $release, $backup, 'production'))->status();
        $encoded = json_encode($status, JSON_THROW_ON_ERROR);

        self::assertSame('blocked', $status['state']);
        self::assertSame('UPGRADE_BACKUP_MANIFEST_INVALID', $status['preflight']['code']);
        self::assertStringNotContainsString($release, $encoded);
        self::assertStringNotContainsString($backup, $encoded);
    }

    /** @param array<string, mixed> $value */
    private function temporaryJson(array $value): string
    {
        $path = tempnam(sys_get_temp_dir(), 'peanut-upgrade-status-');
        self::assertIsString($path);
        file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
