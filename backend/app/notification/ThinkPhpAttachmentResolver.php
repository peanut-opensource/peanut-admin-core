<?php

declare(strict_types=1);

namespace PeanutAdmin\App\notification;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\NotificationSms\Application\AttachmentReference;
use PeanutAdmin\NotificationSms\Application\AttachmentResolver;
use PeanutAdmin\NotificationSms\Application\NotificationException;
use think\facade\Db;

final readonly class ThinkPhpAttachmentResolver implements AttachmentResolver
{
    public function snapshot(TenantContext $context, string $fileKey): AttachmentReference
    {
        if (preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw NotificationException::attachmentUnavailable();
        }
        $row = Db::name('file_object')
            ->where('tenant_id', $context->tenantId)
            ->where('file_key', $fileKey)
            ->where('status', 'ready')
            ->field('original_name,media_type,size_bytes,sha256')
            ->find();
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
