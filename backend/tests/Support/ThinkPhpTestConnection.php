<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Support;

use PDO;
use RuntimeException;
use think\Container;
use think\DbManager;
use think\db\ConnectionInterface;
use think\db\PDOConnection;
use think\db\builder\Mysql as MysqlBuilder;
use think\db\builder\Sqlite as SqliteBuilder;
use think\db\connector\Mysql;
use think\db\connector\Sqlite;

final class ThinkPhpTestConnection
{
    private function __construct() {}

    public static function fromPdo(PDO $pdo): PDOConnection
    {
        $connection = match ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => new SharedPdoMysqlConnection($pdo),
            'sqlite' => new SharedPdoSqliteConnection($pdo),
            default => throw new RuntimeException('Test database driver is unsupported.'),
        };
        $manager = new SharedPdoDbManager($connection);
        $connection->setDb($manager);
        Container::getInstance()->instance(DbManager::class, $manager);

        return $connection;
    }
}

/** Registers the exact fixture connection as ThinkPHP's default database manager. */
final class SharedPdoDbManager extends DbManager
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
        parent::__construct();
    }

    /** @param array<array-key, mixed>|string|null $name */
    protected function instance(string|array|null $name = null, bool $force = false): ConnectionInterface
    {
        return $this->connection;
    }
}

final class SharedPdoMysqlConnection extends Mysql
{
    public function __construct(private readonly PDO $sharedPdo)
    {
        parent::__construct([
            'type' => 'mysql',
            'builder' => MysqlBuilder::class,
            'prefix' => 'pa_',
        ]);
    }

    /**
     * @param mixed $dsn
     * @param mixed $username
     * @param mixed $password
     * @param mixed $params
     */
    protected function createPdo($dsn, $username, $password, $params): PDO
    {
        return $this->sharedPdo;
    }
}

final class SharedPdoSqliteConnection extends Sqlite
{
    public function __construct(private readonly PDO $sharedPdo)
    {
        parent::__construct([
            'type' => 'sqlite',
            'builder' => SqliteBuilder::class,
            'prefix' => 'pa_',
        ]);
    }

    /**
     * @param mixed $dsn
     * @param mixed $username
     * @param mixed $password
     * @param mixed $params
     */
    protected function createPdo($dsn, $username, $password, $params): PDO
    {
        return $this->sharedPdo;
    }
}
