<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Smoke;

use PDO;
use PeanutAdmin\App\notification\NotificationRuntimeFactory;
use PeanutAdmin\Kernel\Override\OverrideException;
use PeanutAdmin\Kernel\Override\ServiceOverrideRegistry;
use PeanutAdmin\NotificationSms\Sms\DisabledSmsProvider;
use PeanutAdmin\NotificationSms\Sms\LocalDevSmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use PeanutAdmin\TaskJob\Execution\LocalWorker;
use PHPUnit\Framework\TestCase;
use think\App;

final class ServiceOverrideHostWiringTest extends TestCase
{
    private mixed $initialErrorHandler;

    private mixed $initialExceptionHandler;

    protected function setUp(): void
    {
        $this->initialErrorHandler = self::currentErrorHandler();
        $this->initialExceptionHandler = self::currentExceptionHandler();
        putenv('PEANUT_SMS_PROVIDER_IMPLEMENTATION');
        putenv('PEANUT_TASK_ENVELOPE_KEY=' . str_repeat('x', 32));
    }

    protected function tearDown(): void
    {
        putenv('PEANUT_SMS_PROVIDER_IMPLEMENTATION');
        putenv('PEANUT_TASK_ENVELOPE_KEY');
        if (self::currentErrorHandler() !== $this->initialErrorHandler) {
            restore_error_handler();
        }
        if (self::currentExceptionHandler() !== $this->initialExceptionHandler) {
            restore_exception_handler();
        }
    }

    public function testHostBindsDisabledDefaultAndFactoryConsumesIt(): void
    {
        $app = $this->bootHost();

        $registry = $app->make(ServiceOverrideRegistry::class);
        self::assertSame('default', $registry->resolve('peanut.notification.service.sms-provider')->source);
        self::assertInstanceOf(DisabledSmsProvider::class, $app->make(SmsProvider::class));
        self::assertInstanceOf(
            LocalWorker::class,
            NotificationRuntimeFactory::worker(new PDO('sqlite::memory:'), 1, 'worker_default'),
        );
    }

    public function testHostBindsExplicitApplicationOverride(): void
    {
        putenv('PEANUT_SMS_PROVIDER_IMPLEMENTATION=' . LocalDevSmsProvider::class);
        $app = $this->bootHost();

        $registry = $app->make(ServiceOverrideRegistry::class);
        self::assertSame('application', $registry->resolve('peanut.notification.service.sms-provider')->source);
        self::assertInstanceOf(LocalDevSmsProvider::class, $app->make(SmsProvider::class));
        self::assertInstanceOf(
            LocalWorker::class,
            NotificationRuntimeFactory::worker(new PDO('sqlite::memory:'), 1, 'worker_override'),
        );
    }

    public function testInvalidApplicationOverrideFailsHostStartup(): void
    {
        putenv('PEANUT_SMS_PROVIDER_IMPLEMENTATION=' . \stdClass::class);

        try {
            $this->bootHost();
            self::fail('Invalid service override did not fail Host startup.');
        } catch (OverrideException $exception) {
            self::assertSame('PHP_OVERRIDE_IMPLEMENTATION_INVALID', $exception->errorCode);
        }
    }

    private function bootHost(): App
    {
        $app = new App(dirname(__DIR__, 2));
        $app->initialize();

        return $app;
    }

    private static function currentErrorHandler(): mixed
    {
        $current = set_error_handler(static fn(): bool => false);
        restore_error_handler();

        return $current;
    }

    private static function currentExceptionHandler(): mixed
    {
        $current = set_exception_handler(static function (\Throwable $exception): void {});
        restore_exception_handler();

        return $current;
    }
}
