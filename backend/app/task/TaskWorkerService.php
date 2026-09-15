<?php

declare(strict_types=1);

namespace PeanutAdmin\App\task;

use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\Kernel\Async\AsyncAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\JobHandlerAdapter;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\NotificationSms\Persistence\NotificationRepository;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsRecipientResolver;
use PeanutAdmin\NotificationSms\Task\InboxTaskHandler;
use PeanutAdmin\NotificationSms\Task\SmsTaskHandler;
use PeanutAdmin\TaskJob\Execution\LocalWorker;
use PeanutAdmin\TaskJob\Execution\TaskHandlerRegistry;
use PeanutAdmin\TaskJob\Persistence\TaskJobStore;

final readonly class TaskWorkerService
{
    public function __construct(
        private TaskJobStore $jobs,
        private NotificationRepository $notifications,
        private SmsRecipientResolver $smsRecipients,
        private SmsProvider $smsProvider,
        private ImportExportTaskHandler $imports,
        private TrustedEnvelopeCodec $codec,
        private AsyncAuthorizationRevalidator $authorization,
    ) {}

    public function runOnce(int $tenantId, string $workerId): ?string
    {
        return (new LocalWorker(
            $tenantId,
            $workerId,
            $this->jobs,
            new TaskHandlerRegistry([
                new InboxTaskHandler($this->notifications),
                new SmsTaskHandler($this->notifications, $this->smsRecipients, $this->smsProvider),
                $this->imports,
            ]),
            new JobHandlerAdapter($this->codec, $this->authorization),
        ))->runOnce();
    }
}
