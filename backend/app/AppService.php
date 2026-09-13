<?php

declare(strict_types=1);

namespace PeanutAdmin\App;

use PDO;
use PeanutAdmin\Kernel\Override\ServiceOverrideRegistry;
use PeanutAdmin\Kernel\Override\ServiceOverrideSlot;
use PeanutAdmin\Kernel\Persistence\ThinkPhp\ThinkPhpTransactionManager;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use PeanutAdmin\NotificationSms\Sms\DisabledSmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use RuntimeException;
use think\db\ConnectionInterface;
use think\db\PDOConnection;
use think\facade\Db;
use think\Service;

final class AppService extends Service
{
    public function register(): void
    {
        $overrides = require dirname(__DIR__) . '/config/service-overrides.php';
        if (!is_array($overrides)) {
            throw new RuntimeException('SERVICE_OVERRIDES_CONFIG_INVALID');
        }

        $registry = new ServiceOverrideRegistry([
            new ServiceOverrideSlot(
                'peanut.notification.service.sms-provider',
                SmsProvider::class,
                '1.0.0',
                DisabledSmsProvider::class,
            ),
        ], $overrides);
        $this->app->instance(ServiceOverrideRegistry::class, $registry);
        foreach ($registry->bindings() as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        $this->app->bind(ConnectionInterface::class, static fn(): ConnectionInterface => Db::connect());
        $this->app->bind(PDOConnection::class, function (): PDOConnection {
            $connection = $this->app->make(ConnectionInterface::class);
            if (!$connection instanceof PDOConnection) {
                throw new RuntimeException('DATABASE_CONNECTION_UNSUPPORTED');
            }

            return $connection;
        });
        $this->app->bind(PDO::class, fn(): PDO => $this->app->make(PDOConnection::class)->connect());
        $this->app->bind(TransactionManager::class, fn(): TransactionManager => new ThinkPhpTransactionManager(
            $this->app->make(PDOConnection::class),
        ));
    }
}
