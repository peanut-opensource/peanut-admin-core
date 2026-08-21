<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Security;

use PHPUnit\Framework\TestCase;

final class FileMediaSecurityTest extends TestCase
{
    public function testPublicShapesExcludeTenantStorageAndActorInternals(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/packages/php/file-media/src/Application/FileObject.php') ?: '';
        $array = substr($source, strpos($source, 'public function toArray') ?: 0);
        $array = substr($array, 0, strpos($array, 'public function auditMetadata') ?: strlen($array));
        foreach (['tenant_id', 'created_by_member_id', 'storage_provider_key', 'storage_key', 'sourcePath'] as $forbidden) {
            self::assertStringNotContainsString("'{$forbidden}'", $array);
        }
    }

    public function testErrorsAndAuditNeverExposeInfrastructureOrContent(): void
    {
        $errors = file_get_contents(dirname(__DIR__, 3) . '/packages/php/file-media/src/Application/FileMediaException.php') ?: '';
        foreach (['storage_key', 'sourcePath', 'PDOException', 'getTrace', 'SQLSTATE'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $errors);
        }
        $object = file_get_contents(dirname(__DIR__, 3) . '/packages/php/file-media/src/Application/FileObject.php') ?: '';
        foreach (['content', 'token', 'ip_address', 'user_agent'] as $forbidden) {
            self::assertStringNotContainsString("'{$forbidden}'", $object);
        }
    }

    public function testEveryLookupAndMutationIsTenantScoped(): void
    {
        $repository = file_get_contents(dirname(__DIR__, 3) . '/packages/php/file-media/src/Persistence/PdoFileRepository.php') ?: '';
        self::assertGreaterThanOrEqual(4, substr_count($repository, 'tenant_id = :tenant_id'));
        self::assertStringContainsString("AND status = 'ready'", $repository);
        self::assertStringContainsString('FOR UPDATE', $repository);
    }
}
