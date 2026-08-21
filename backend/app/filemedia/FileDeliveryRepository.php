<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use PDO;
use PeanutAdmin\FileMedia\Application\FileObject;
use PeanutAdmin\FileMedia\Media\ImageMetadataInspector;

final readonly class FileDeliveryRepository
{
    public function __construct(private PDO $pdo) {}

    public function recordImage(FileObject $file, string $sourcePath): void
    {
        if (!in_array($file->mediaType, ['image/jpeg','image/png'], true)) {
            return;
        }
        $metadata = (new ImageMetadataInspector())->inspect($sourcePath);
        $statement = $this->pdo->prepare('INSERT INTO pa_file_image_metadata (tenant_id,file_object_id,width,height,media_type,created_at) VALUES (:tenant_id,:file_id,:width,:height,:media_type,UTC_TIMESTAMP(3))');
        $statement->execute(['tenant_id' => $file->tenantId,'file_id' => $file->id,'width' => $metadata->width,'height' => $metadata->height,'media_type' => $metadata->mediaType]);
        $policy = $this->pdo->prepare("INSERT INTO pa_file_delivery_policy (tenant_id,file_object_id,visibility,revision,updated_at) VALUES (:tenant_id,:file_id,'private',1,UTC_TIMESTAMP(3))");
        $policy->execute(['tenant_id' => $file->tenantId,'file_id' => $file->id]);
    }

    /** @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function assets(int $tenantId, int $page, int $pageSize): array
    {
        $offset = ($page - 1) * $pageSize;
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM pa_file_image_metadata im JOIN pa_file_object f ON f.tenant_id=im.tenant_id AND f.id=im.file_object_id WHERE f.tenant_id=:tenant_id AND f.status='ready'");
        $count->execute(['tenant_id' => $tenantId]);
        $query = $this->pdo->prepare("SELECT f.file_key,f.original_name,f.media_type,im.width,im.height FROM pa_file_image_metadata im JOIN pa_file_object f ON f.tenant_id=im.tenant_id AND f.id=im.file_object_id WHERE f.tenant_id=:tenant_id AND f.status='ready' ORDER BY f.id DESC LIMIT :limit OFFSET :offset");
        $query->bindValue('tenant_id', $tenantId, PDO::PARAM_INT);
        $query->bindValue('limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue('offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return ['items' => array_values($query->fetchAll(PDO::FETCH_ASSOC)),'page' => $page,'page_size' => $pageSize,'total' => (int) $count->fetchColumn()];
    }
}
