<?php

declare(strict_types=1);

namespace PeanutAdmin\App\database;

use RuntimeException;
use think\DbManager;
use think\db\PDOConnection;

final class ThinkPhpConnectionFactory
{
    private function __construct() {}

    public static function fromEnvironment(string $root): PDOConnection
    {
        /** @var array{default: string, connections: array<string, array<string, mixed>>} $config */
        $config = require $root . '/backend/config/database.php';

        return self::fromConfig($config);
    }

    /** @param array{default: string, connections: array<string, array<string, mixed>>} $config */
    public static function fromConfig(array $config): PDOConnection
    {
        $manager = new DbManager();
        $manager->setConfig($config);
        $connection = $manager->connect();
        if (!$connection instanceof PDOConnection) {
            throw new RuntimeException('DATABASE_CONNECTION_UNSUPPORTED');
        }

        return $connection;
    }
}
