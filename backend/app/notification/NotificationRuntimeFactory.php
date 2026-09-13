<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PDO;
use PeanutAdmin\App\importexport\ImportExportRuntimeFactory;
use PeanutAdmin\App\task\PdoTaskAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\JobHandlerAdapter;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\Kernel\Persistence\ThinkPhp\ThinkPhpTransactionManager;
use PeanutAdmin\NotificationSms\Application\NotificationService;
use PeanutAdmin\NotificationSms\Application\TemplateRenderer;
use PeanutAdmin\NotificationSms\Persistence\NotificationStore;
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
use think\db\PDOConnection;
use think\facade\Db;

final class NotificationRuntimeFactory
{
    public static function service(PDOConnection $connection): NotificationService
    {
        $config = self::config();
        $pdo = $connection->connect();
        $recipients = new PdoRecipientResolver($pdo, $config['recipient_directory'], $config['recipient_digest_key']);
        return new NotificationService(
            new NotificationStore($connection),
            new ThinkPhpTransactionManager($connection),
            $recipients,
            new PdoAttachmentResolver($pdo),
            new TemplateRenderer(),
        );
    }

    public static function dispatcher(PDOConnection $connection): NotificationOutboxDispatcher
    {
        return new NotificationOutboxDispatcher(
            new NotificationStore($connection),
            new ThinkPhpTransactionManager($connection),
            self::publisher($connection->connect()),
        );
    }

    public static function worker(
        PDOConnection $connection,
        int $tenantId,
        string $workerId,
        ?SmsProvider $smsProvider = null,
    ): LocalWorker {
        $config = self::config();
        $pdo = $connection->connect();
        $smsProvider ??= Container::getInstance()->make(SmsProvider::class);
        $recipients = new PdoRecipientResolver($pdo, $config['recipient_directory'], $config['recipient_digest_key']);
        $transactions = new ThinkPhpTransactionManager($connection);
        $repository = new NotificationStore($connection);
        $handlers = new TaskHandlerRegistry([
            new InboxTaskHandler($repository, $transactions),
            new SmsTaskHandler($repository, $transactions, $recipients, $smsProvider),
            ImportExportRuntimeFactory::handler($pdo),
        ]);
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

    public static function connection(): PDOConnection
    {
        $connection = Db::connect();
        if (!$connection instanceof PDOConnection) {
            throw new RuntimeException('NOTIFICATION_DATABASE_CONNECTION_UNSUPPORTED');
        }

        return $connection;
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
