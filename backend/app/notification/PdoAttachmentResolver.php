<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\NotificationSms\Application\AttachmentReference;
use PeanutAdmin\NotificationSms\Application\AttachmentResolver;
use PeanutAdmin\NotificationSms\Application\NotificationException;

final readonly class PdoAttachmentResolver implements AttachmentResolver
{
    public function __construct(private PDO $pdo) {}

    public function snapshot(TenantContext $context, string $fileKey): AttachmentReference
    {
        if (preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw NotificationException::attachmentUnavailable();
        }
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT original_name, media_type, size_bytes, sha256
FROM pa_file_object
WHERE tenant_id = :tenant_id AND file_key = :file_key AND status = 'ready'
SQL);
        $statement->execute(['tenant_id' => $context->tenantId, 'file_key' => $fileKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw NotificationException::attachmentUnavailable();
        }
        return new AttachmentReference(
            $fileKey,
            (string) $row['original_name'],
            (string) $row['media_type'],
            (int) $row['size_bytes'],
            (string) $row['sha256'],
        );
    }
}
