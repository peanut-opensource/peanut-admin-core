<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Install;

use PeanutAdmin\App\command\InstallEnvironmentChecker;
use PHPUnit\Framework\TestCase;

final class EnvironmentCheckerTest extends TestCase
{
    public function testPreflightReportsAllFailuresBeforeChangingState(): void
    {
        $checker = new InstallEnvironmentChecker(
            dirname(__DIR__, 3),
            '8.2.9',
            ['json', 'pdo'],
        );

        $report = $checker->check();

        self::assertFalse($report['ready']);
        self::assertContains('PHP 8.3 or newer is required.', $report['errors']);
        self::assertContains('Required PHP extension is missing: pdo_mysql.', $report['errors']);
    }

    public function testCurrentRuntimePassesTheStaticEnvironmentChecks(): void
    {
        $report = (new InstallEnvironmentChecker(dirname(__DIR__, 3)))->check();

        self::assertTrue($report['ready'], implode("\n", $report['errors']));
    }
}
