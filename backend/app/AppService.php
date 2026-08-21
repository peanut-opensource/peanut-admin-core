<?php

declare(strict_types=1);

namespace PeanutAdmin\App;

use PDO;
use PeanutAdmin\Kernel\Override\ServiceOverrideRegistry;
use PeanutAdmin\Kernel\Override\ServiceOverrideSlot;
use PeanutAdmin\NotificationSms\Sms\DisabledSmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use RuntimeException;
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

        $this->app->bind(PDO::class, function (): PDO {
            $host     = getenv('DB_HOST') ?: '127.0.0.1';
            $port     = (int) (getenv('DB_PORT') ?: 3306);
            $database = getenv('DB_DATABASE') ?: 'peanut_admin';
            $username = getenv('DB_USERNAME') ?: 'peanut_admin';
            $password = getenv('DB_PASSWORD') ?: 'peanut_admin_dev';

            return new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ],
            );
        });
    }
}
