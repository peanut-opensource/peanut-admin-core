<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Unit;

use PeanutAdmin\App\filemedia\LocalPrivateStorageProvider;
use PeanutAdmin\FileMedia\Application\FileMediaException;
use PHPUnit\Framework\TestCase;

final class FileMediaLocalStorageTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/peanut-file-storage-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->base, 0700));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);
    }

    public function testItStoresOpensAndRemovesPrivateBytes(): void
    {
        $source = $this->base . '/source.txt';
        file_put_contents($source, "private bytes\n");
        $provider = new LocalPrivateStorageProvider($this->base . '/objects', [$this->base . '/public']);

        $object = $provider->store(7, 'file_0123456789abcdef0123456789abcdef', $source);
        self::assertSame('local-private', $object->providerKey);
        self::assertStringNotContainsString($this->base, $object->storageKey);
        $stream = $provider->open($object->storageKey);
        self::assertSame("private bytes\n", stream_get_contents($stream));
        fclose($stream);
        $provider->remove($object->storageKey);
        $this->assertFileError(fn() => $provider->open($object->storageKey));
    }

    public function testItRejectsPublicRootsAndTraversalStorageKeys(): void
    {
        $public = $this->base . '/public';
        mkdir($public, 0700);
        $this->assertFileError(fn() => new LocalPrivateStorageProvider($public . '/files', [$public]));

        $provider = new LocalPrivateStorageProvider($this->base . '/objects', [$public]);
        foreach (['../secret', '/absolute', "bad\0key", 'tenant_x/file.bin'] as $key) {
            $this->assertFileError(fn() => $provider->open($key));
        }
    }

    public function testItRejectsSymlinkTraversalBelowTheRoot(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink is unavailable');
        }
        $outside = $this->base . '/outside';
        mkdir($outside, 0700);
        $root = $this->base . '/objects';
        $provider = new LocalPrivateStorageProvider($root, [$this->base . '/public']);
        $tenant = 'tenant_' . substr(hash('sha256', 'peanut-file-tenant:7'), 0, 32);
        symlink($outside, $root . '/' . $tenant);
        $source = $this->base . '/source.txt';
        file_put_contents($source, 'private');

        $this->assertFileError(fn() => $provider->store(7, 'file_0123456789abcdef0123456789abcdef', $source));
    }

    public function testItRejectsAPrivateRootAliasedBelowAPublicRootBeforeCreatingIt(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink is unavailable');
        }
        $public = $this->base . '/public';
        mkdir($public, 0700);
        $alias = $this->base . '/public-alias';
        symlink($public, $alias);

        $this->assertFileError(fn() => new LocalPrivateStorageProvider($alias . '/files', [$public]));
        self::assertDirectoryDoesNotExist($public . '/files');
    }

    private function assertFileError(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected private storage failure');
        } catch (FileMediaException $exception) {
            self::assertSame('FILE_STORAGE_UNAVAILABLE', $exception->errorCode);
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        foreach (glob($path . '/*') ?: [] as $child) {
            $this->removeTree($child);
        }
        @rmdir($path);
    }
}
