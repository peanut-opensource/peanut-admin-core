<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PDO;
use PeanutAdmin\App\importexport\ImportExportRuntimeFactory;
use PeanutAdmin\App\task\PdoTaskAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\JobHandlerAdapter;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\NotificationSms\Application\NotificationService;
use PeanutAdmin\NotificationSms\Application\TemplateRenderer;
use PeanutAdmin\NotificationSms\Persistence\PdoNotificationRepository;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use PeanutAdmin\NotificationSms\Task\InboxTaskHandler;
use PeanutAdmin\NotificationSms\Task\NotificationOutboxDispatcher;
use PeanutAdmin\NotificationSms\Task\OutboxTaskSubmissionProvider;
use PeanutAdmin\NotificationSms\Task\SmsTaskHandler;
use PeanutAdmin\TaskJob\Execution\LocalWorker;
use PeanutAdmin\TaskJob\Execution\TaskHandlerRegistry;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionRegistry;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use RuntimeException;
use think\Container;

final class NotificationRuntimeFactory
{
    public static function service(PDO $pdo): NotificationService
    {
        $config = self::config();
        $recipients = new PdoRecipientResolver($pdo, $config['recipient_directory'], $config['recipient_digest_key']);
        return new NotificationService(new PdoNotificationRepository($pdo), $recipients, new PdoAttachmentResolver($pdo), new TemplateRenderer());
    }

    public static function dispatcher(PDO $pdo): NotificationOutboxDispatcher
    {
        return new NotificationOutboxDispatcher(new PdoNotificationRepository($pdo), self::publisher($pdo));
    }

    public static function worker(
        PDO $pdo,
        int $tenantId,
        string $workerId,
        ?SmsProvider $smsProvider = null,
    ): LocalWorker {
        $config = self::config();
        $smsProvider ??= Container::getInstance()->make(SmsProvider::class);
        $recipients = new PdoRecipientResolver($pdo, $config['recipient_directory'], $config['recipient_digest_key']);
        $repository = new PdoNotificationRepository($pdo);
        $handlers = new TaskHandlerRegistry([new InboxTaskHandler($repository),new SmsTaskHandler($repository, $recipients, $smsProvider),ImportExportRuntimeFactory::handler($pdo)]);
        return new LocalWorker($tenantId, $workerId, new PdoTaskJobRepository($pdo), $handlers, new JobHandlerAdapter(self::codec(), new PdoTaskAuthorizationRevalidator($pdo)));
    }

    private static function publisher(PDO $pdo): TrustedJobPublisher
    {
        return new TrustedJobPublisher(new PdoTaskJobRepository($pdo), new TaskSubmissionRegistry([new OutboxTaskSubmissionProvider('inbox'),new OutboxTaskSubmissionProvider('sms')]), self::codec());
    }

    private static function codec(): TrustedEnvelopeCodec
    {
        $key = self::config()['envelope_key'];
        if (strlen($key) < 32) {
            throw new RuntimeException('TASK_ENVELOPE_KEY_UNAVAILABLE');
        }return new TrustedEnvelopeCodec($key);
    }

    /** @return array{envelope_key:string,recipient_digest_key:string,recipient_directory:array<string,mixed>} */
    private static function config(): array
    {
        $config = require dirname(__DIR__, 3) . '/backend/config/notification-sms.php';
        if (!is_array($config) || !is_string($config['envelope_key'] ?? null) || !is_string($config['recipient_digest_key'] ?? null) || !is_array($config['recipient_directory'] ?? null)) {
            throw new RuntimeException('NOTIFICATION_CONFIG_INVALID');
        }
        return $config;
    }
}
