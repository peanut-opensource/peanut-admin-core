<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Logs\LogSeverity;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProvider;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogQuery;
use PeanutAdmin\OpsConsole\Logs\StructuredLogBatch;
use PeanutAdmin\OpsConsole\Logs\StructuredLogRecord;

final readonly class PdoRuntimeLogProvider implements RuntimeLogProvider
{
    public function __construct(private PDO $pdo) {}
    public function sourceKey(): string
    {
        return 'platform.audit';
    }
    public function read(PlatformContext $context, RuntimeLogQuery $query): StructuredLogBatch
    {
        $after = PHP_INT_MAX;
        if ($query->cursor !== null) {
            $hex = substr($query->cursor, 7);
            if (preg_match('/^[0-9a-f]{1,16}$/D', $hex) !== 1) {
                throw new \InvalidArgumentException('Invalid cursor.');
            }$after = hexdec($hex);
            if (!is_int($after)) {
                throw new \InvalidArgumentException('Invalid cursor.');
            }
        }
        $allowed = array_slice(LogSeverity::VALUES, LogSeverity::rank($query->minimumSeverity));
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $statement = $this->pdo->prepare("SELECT id,event_type,outcome,reason_code,request_id,occurred_at FROM pa_platform_audit_event WHERE id < ? AND (CASE outcome WHEN 'success' THEN 'info' WHEN 'denied' THEN 'warning' ELSE 'error' END) IN ($placeholders) ORDER BY id DESC LIMIT ?");
        $index = 1;
        $statement->bindValue($index++, $after, PDO::PARAM_INT);
        foreach ($allowed as $severity) {
            $statement->bindValue($index++, $severity);
        }$statement->bindValue($index, $query->pageSize, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $records = array_values(array_map(static fn(array $r): StructuredLogRecord => new StructuredLogRecord((string) $r['event_type'], match ($r['outcome']) {
            'success' => 'info','denied' => 'warning',default => 'error',
        }, 'platform', str_replace(' ', 'T', (string) $r['occurred_at']) . 'Z', is_string($r['request_id']) ? $r['request_id'] : null, 1), $rows));
        $last = $rows === [] ? null : (int) $rows[array_key_last($rows)]['id'];
        return new StructuredLogBatch($records, count($rows) === $query->pageSize && $last !== null ? 'cursor_' . dechex($last) : null);
    }
}
