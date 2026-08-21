<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Upgrade;

use PDO;
use PeanutAdmin\App\command\UpgradeWorkflow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileMediaUpgradeTest extends TestCase
{
    private const DATABASE = 'peanut_admin_c02_file_media_upgrade_test';

    private PDO $admin;
    private PDO $pdo;

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through the focused File/Media upgrade gate.');
        }
        $port = $this->requiredPort('DB_PORT');
        if ($port !== $this->requiredPort('MYSQL_PORT') || getenv('DB_HOST') !== '127.0.0.1') {
            throw new RuntimeException('File/Media upgrade requires one explicit local MySQL port.');
        }
        $password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
    }

    public function testCleanUpgradeAndRepeatedUpgradeKeepFileMediaCoexisting(): void
    {
        $workflow = new UpgradeWorkflow(dirname(__DIR__, 3), $this->pdo);
        $first = $workflow->installEmptyDatabase();
        $second = $workflow->assertCurrentReleaseNoop();

        self::assertContains('peanut.file-media', $first['modules']);
        self::assertGreaterThanOrEqual(1, $first['applied_module_migrations']);
        self::assertSame(0, $second['applied_module_migrations']);
        self::assertSame(1, $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM pa_module_migration
WHERE module_key = 'peanut.file-media'
  AND migration_key = 'peanut.file-media:20260723020101_create_file_objects'
  AND status = 'applied'
SQL));
        foreach (['pa_file_object', 'pa_setting_definition', 'pa_reference_code_set'] as $table) {
            self::assertSame(1, $this->scalar(<<<SQL
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = '{$table}'
SQL), $table);
        }
    }

    private function scalar(string $sql): int
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    private function requiredPort(string $name): int
    {
        $value = getenv($name);
        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new RuntimeException("Missing required environment variable: {$name}.");
        }
        $port = (int) $value;
        if ($port < 1024 || $port > 65535) {
            throw new RuntimeException("Invalid local port in environment variable: {$name}.");
        }

        return $port;
    }
}
