<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Http;

use PeanutAdmin\App\filemedia\FileRuntimeFactory;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PHPUnit\Framework\TestCase;

final class FileMediaApiTest extends TestCase
{
    public function testDefinesTheFiveTenantPrivateOperations(): void
    {
        $operations = FileRuntimeFactory::operations();

        self::assertSame(['listFiles', 'createFile', 'getFile', 'downloadFile', 'archiveFile'], array_keys($operations));
        $this->assertOperation($operations['listFiles'], 'GET', '/api/v1/files', 'peanut.file-media.read', false);
        $this->assertOperation($operations['createFile'], 'POST', '/api/v1/files', 'peanut.file-media.create', true);
        $this->assertOperation($operations['getFile'], 'GET', '/api/v1/files/{file_key}', 'peanut.file-media.read', false);
        $this->assertOperation($operations['downloadFile'], 'GET', '/api/v1/files/{file_key}/content', 'peanut.file-media.read', false);
        $this->assertOperation($operations['archiveFile'], 'DELETE', '/api/v1/files/{file_key}', 'peanut.file-media.delete', true);
    }

    public function testGeneratedRoutesCarryModulePermissionAndBinaryContract(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';
        self::assertSame('peanut.file-media', $routes['GET /api/v1/files'][7]);
        self::assertSame('peanut.file-media.read', $routes['GET /api/v1/files'][2]);
        self::assertSame('multipart/form-data', $this->requestMediaType('createFile'));
        self::assertSame('*/*', $routes['GET /api/v1/files/{file_key}/content'][9]);
        self::assertContains('Content-Disposition', $routes['GET /api/v1/files/{file_key}/content'][10]);
        self::assertNull($routes['GET /api/v1/file-deliveries/{file_key}'][2]);
        self::assertFalse($routes['GET /api/v1/file-deliveries/{file_key}'][5]);
        self::assertNull($routes['GET /api/v1/file-deliveries/{file_key}'][7]);
    }

    public function testManifestAndResourcesExposeOnlyTheBoundedCapability(): void
    {
        $root = dirname(__DIR__, 3) . '/backend/app/Modules/Peanut/FileMedia';
        $manifest = json_decode(file_get_contents($root . '/module.json') ?: '', true, 32, JSON_THROW_ON_ERROR);
        $permissions = json_decode(file_get_contents($root . '/Resources/permissions.json') ?: '', true, 32, JSON_THROW_ON_ERROR);
        $menus = json_decode(file_get_contents($root . '/Resources/menus.json') ?: '', true, 32, JSON_THROW_ON_ERROR);

        self::assertSame('peanut.file-media', $manifest['key']);
        self::assertSame([
            'pa_file_object',
            'pa_file_delivery_policy',
            'pa_file_image_metadata',
            'pa_file_image_variant',
            'pa_file_delivery_nonce',
        ], $manifest['database']['owned_tables']);
        self::assertSame([
            'peanut.file-media.read',
            'peanut.file-media.create',
            'peanut.file-media.delete',
            'peanut.file-media.manage',
        ], array_column($permissions, 'key'));
        self::assertSame('/app/files', $menus[0]['route_path']);
        self::assertSame('peanut.file-media.read', $menus[0]['required_permission']);
    }

    private function assertOperation(
        ExternalOperationDefinition $operation,
        string $method,
        string $path,
        string $permission,
        bool $atomic,
    ): void {
        self::assertSame($method, $operation->method);
        self::assertSame($path, $operation->path);
        self::assertSame('tenant', $operation->audience);
        self::assertSame('peanut.file-media', $operation->moduleKey);
        self::assertSame([$permission], $operation->permission->permissionKeys);
        self::assertSame($atomic, $operation->atomicCommand);
    }

    private function requestMediaType(string $operationId): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/docs/api/openapi.yaml') ?: '';
        self::assertStringContainsString("operationId: {$operationId}", $source);
        self::assertStringContainsString('multipart/form-data:', $source);

        return 'multipart/form-data';
    }
}
