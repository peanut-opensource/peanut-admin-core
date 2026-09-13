<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Support;

use PDO;
use PeanutAdmin\App\database\ThinkPhpConnectionFactory;
use RuntimeException;
use think\db\PDOConnection;

final class ThinkPhpTestConnection
{
    private function __construct() {}

    public static function fromPdo(PDO $pdo): PDOConnection
    {
        $databaseStatement = $pdo->query('SELECT DATABASE()');
        $userStatement = $pdo->query('SELECT CURRENT_USER()');
        $database = $databaseStatement === false ? false : $databaseStatement->fetchColumn();
        $currentUser = $userStatement === false ? false : $userStatement->fetchColumn();
        if (!is_string($database) || $database === '' || !is_string($currentUser) || $currentUser === '') {
            throw new RuntimeException('Test database session is unavailable.');
        }
        $username = explode('@', $currentUser, 2)[0];
        $password = $username === 'root'
            ? (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev')
            : (getenv('DB_PASSWORD') ?: 'peanut_admin_dev');

        return ThinkPhpConnectionFactory::fromConfig([
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'type' => 'mysql',
                    'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'hostport' => (int) (getenv('DB_PORT') ?: 3306),
                    'charset' => 'utf8mb4',
                    'prefix' => 'pa_',
                    'fields_strict' => true,
                    'break_reconnect' => false,
                ],
            ],
        ]);
    }
}
