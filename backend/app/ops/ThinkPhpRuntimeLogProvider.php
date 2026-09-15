<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use InvalidArgumentException;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Logs\LogSeverity;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProvider;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogQuery;
use PeanutAdmin\OpsConsole\Logs\StructuredLogBatch;
use PeanutAdmin\OpsConsole\Logs\StructuredLogRecord;
use think\facade\Db;

final readonly class ThinkPhpRuntimeLogProvider implements RuntimeLogProvider
{
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
                throw new InvalidArgumentException('Invalid cursor.');
            }
            $after = hexdec($hex);
            if (!is_int($after)) {
                throw new InvalidArgumentException('Invalid cursor.');
            }
        }
        $allowed = array_slice(LogSeverity::VALUES, LogSeverity::rank($query->minimumSeverity));
        $rows = Db::name('platform_audit_event')->where('id', '<', $after)
            ->where(function ($builder) use ($allowed): void {
                $outcomes = [];
                if (in_array('info', $allowed, true)) {
                    $outcomes[] = 'success';
                }
                if (in_array('warning', $allowed, true)) {
                    $outcomes[] = 'denied';
                }
                if (array_intersect(['error', 'critical'], $allowed) !== []) {
                    $builder->whereNotIn('outcome', ['success', 'denied']);
                }
                if ($outcomes !== []) {
                    $builder->whereOrIn('outcome', $outcomes);
                }
            })->order('id', 'desc')->limit($query->pageSize)
            ->field('id,event_type,outcome,reason_code,request_id,occurred_at')->select()->toArray();
        $records = array_values(array_map(static fn(array $row): StructuredLogRecord => new StructuredLogRecord(
            (string) $row['event_type'],
            match ($row['outcome']) { 'success' => 'info', 'denied' => 'warning', default => 'error' },
            'platform', str_replace(' ', 'T', (string) $row['occurred_at']) . 'Z',
            is_string($row['request_id']) ? $row['request_id'] : null, 1,
        ), $rows));
        $last = $rows === [] ? null : (int) $rows[array_key_last($rows)]['id'];

        return new StructuredLogBatch(
            $records,
            count($rows) === $query->pageSize && $last !== null ? 'cursor_' . dechex($last) : null,
        );
    }
}
