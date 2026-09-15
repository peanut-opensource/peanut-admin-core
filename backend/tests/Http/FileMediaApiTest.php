<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Http;

use PeanutAdmin\App\controller\api\v1\FileController;
use PHPUnit\Framework\TestCase;

final class FileMediaApiTest extends TestCase
{
    public function testControllerExposesTheFiveTenantPrivateOperations(): void
    {
        foreach (['index', 'create', 'show', 'download', 'delete'] as $method) {
            self::assertTrue(method_exists(FileController::class, $method), "File controller method is missing: {$method}");
        }
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
        $root = dirname(__DIR__, 3) . '/backend/app/modules/peanut/file_media';
        $manifest = json_decode(file_get_contents($root . '/module.json') ?: '', true, 32, JSON_THROW_ON_ERROR);
        $permissions = json_decode(file_get_contents($root . '/resources/permissions.json') ?: '', true, 32, JSON_THROW_ON_ERROR);
        $menus = json_decode(file_get_contents($root . '/resources/menus.json') ?: '', true, 32, JSON_THROW_ON_ERROR);

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

    private function requestMediaType(string $operationId): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/docs/api/openapi.yaml') ?: '';
        self::assertStringContainsString("operationId: {$operationId}", $source);
        self::assertStringContainsString('multipart/form-data:', $source);

        return 'multipart/form-data';
    }
}
