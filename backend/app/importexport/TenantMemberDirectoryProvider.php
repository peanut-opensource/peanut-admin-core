<?php

declare(strict_types=1);

namespace PeanutAdmin\App\importexport;

use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\Contract\ColumnDefinition;
use PeanutAdmin\ImportExport\Contract\DataProvider;
use PeanutAdmin\ImportExport\Contract\ExportBatch;
use PeanutAdmin\ImportExport\Contract\RowIssue;
use PeanutAdmin\ImportExport\Contract\SchemaDefinition;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

final readonly class TenantMemberDirectoryProvider implements DataProvider
{
    public function __construct(private PDO $pdo) {}
    public function key(): string
    {
        return 'tenant.member-directory';
    }
    public function schema(): SchemaDefinition
    {
        return new SchemaDefinition('member-directory.v1', [
            new ColumnDefinition('member_no', 'member_no', true, true, true, 64),
            new ColumnDefinition('display_name', 'display_name', true, true, false, 120),
            new ColumnDefinition('member_type', 'member_type', false, true, false, 32),
            new ColumnDefinition('status', 'status', false, true, false, 32),
        ]);
    }
    /**
     * @param array<string, string|null> $row
     * @return list<RowIssue>
     */
    public function validateImport(AuthorizedOperationContext $context, array $row): array
    {
        $issues = [];
        $number = $row['member_no'] ?? null;
        $name = $row['display_name'] ?? null;
        if (!is_string($number) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $number) !== 1) {
            $issues[] = new RowIssue('IMPORT_MEMBER_NO_INVALID', 'member_no');
        }
        if ($name !== null && (trim($name) === '' || mb_strlen($name) > 120)) {
            $issues[] = new RowIssue('IMPORT_DISPLAY_NAME_INVALID', 'display_name');
        }
        return $issues;
    }
    public function importRow(AuthorizedOperationContext $context, array $row, string $idempotencyKey): void
    {
        if ($this->validateImport($context, $row) !== []) {
            throw ImportExportException::schemaMismatch();
        }
        $statement = $this->pdo->prepare('UPDATE pa_tenant_member SET display_name=:display_name, updated_at=UTC_TIMESTAMP(3) WHERE tenant_id=:tenant_id AND member_no=:member_no AND status<>\'left\'');
        $statement->execute(['display_name' => $row['display_name'],'tenant_id' => $context->tenantContext->tenantId,'member_no' => $row['member_no']]);
        if ($statement->rowCount() > 1) {
            throw ImportExportException::internal();
        }
    }
    public function exportBatch(AuthorizedOperationContext $context, ?string $cursor, int $limit): ExportBatch
    {
        $after = $cursor === null ? 0 : filter_var($cursor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($after) || $limit < 1 || $limit > 500) {
            throw ImportExportException::invalid();
        }
        $statement = $this->pdo->prepare('SELECT id,member_no,display_name,member_type,status FROM pa_tenant_member WHERE tenant_id=:tenant_id AND id>:after ORDER BY id LIMIT :limit');
        $statement->bindValue('tenant_id', $context->tenantContext->tenantId, PDO::PARAM_INT);
        $statement->bindValue('after', $after, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $next = count($rows) === $limit ? (string) $rows[array_key_last($rows)]['id'] : null;
        return new ExportBatch(array_values(array_map(static fn(array $r): array => ['member_no' => self::exportValue($r['member_no'] ?? null),'display_name' => self::exportValue($r['display_name'] ?? null),'member_type' => self::exportValue($r['member_type'] ?? null),'status' => self::exportValue($r['status'] ?? null)], $rows)), $next);
    }

    private static function exportValue(mixed $value): bool|float|int|string|null
    {
        if ($value === null || is_bool($value) || is_float($value) || is_int($value) || is_string($value)) {
            return $value;
        }
        throw ImportExportException::internal();
    }
}
