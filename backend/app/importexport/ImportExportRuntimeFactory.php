<?php

declare(strict_types=1);

namespace PeanutAdmin\App\importexport;

use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskSubmissionProvider;
use PeanutAdmin\ImportExport\Persistence\ImportExportStore;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\ThinkPhp\ThinkPhpTransactionManager;
use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Persistence\TaskJobStore;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionRegistry;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use think\db\PDOConnection;

final class ImportExportRuntimeFactory
{
    public static function service(PDOConnection $connection): ImportExportService
    {
        $pdo = $connection->connect();
        $transactions = new ThinkPhpTransactionManager($connection);
        $jobs = new TaskJobStore($connection);
        return new ImportExportService(new ImportExportStore($connection), $transactions, self::providers($pdo), new TrustedJobPublisher($jobs, $transactions, new TaskSubmissionRegistry([new ImportExportTaskSubmissionProvider()]), self::codec()), new TaskJobService($jobs, $transactions), new PdoAuditRepository($pdo));
    }
    public static function handler(PDOConnection $connection): ImportExportTaskHandler
    {
        $pdo = $connection->connect();
        $transactions = new ThinkPhpTransactionManager($connection);
        $repository = new ImportExportStore($connection);
        return new ImportExportTaskHandler(new CsvOperationRunner($repository, $transactions, self::providers($pdo), new PdoFileMediaGateway($pdo), new PdoAuditRepository($pdo)));
    }
    private static function providers(PDO $pdo): DataProviderRegistry
    {
        return new DataProviderRegistry([new TenantMemberDirectoryProvider($pdo)]);
    }
    private static function codec(): TrustedEnvelopeCodec
    {
        $config = require dirname(__DIR__, 2) . '/config/notification-sms.php';
        $key = $config['envelope_key'] ?? null;
        if (!is_string($key) || strlen($key) < 32) {
            throw new \RuntimeException('TASK_ENVELOPE_KEY_UNAVAILABLE');
        }return new TrustedEnvelopeCodec($key);
    }
}
