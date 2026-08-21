<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Storage\StorageProvider;
use PeanutAdmin\FileMedia\Storage\StoredObject;
use Throwable;

final readonly class LocalPrivateStorageProvider implements StorageProvider
{
    private string $root;

    /** @param list<string> $publicRoots */
    public function __construct(string $root, array $publicRoots)
    {
        if (!self::absolute($root)) {
            throw FileMediaException::storageUnavailable();
        }
        $declaredRoot = self::normalize($root);
        if (is_link($declaredRoot)) {
            throw FileMediaException::storageUnavailable();
        }
        $root = self::physical($declaredRoot);
        foreach ($publicRoots as $publicRoot) {
            if (!self::absolute($publicRoot)) {
                throw FileMediaException::storageUnavailable();
            }
            $publicRoot = self::physical(self::normalize($publicRoot));
            if (self::contains($publicRoot, $root) || self::contains($root, $publicRoot)) {
                throw FileMediaException::storageUnavailable();
            }
        }
        self::createDirectory($root);
        $resolved = realpath($root);
        if (!is_string($resolved) || is_link($root)) {
            throw FileMediaException::storageUnavailable();
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function key(): string
    {
        return 'local-private';
    }

    public function store(int $tenantId, string $fileKey, string $sourcePath): StoredObject
    {
        if ($tenantId < 1 || preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw FileMediaException::storageUnavailable();
        }
        $tenantKey = substr(hash('sha256', 'peanut-file-tenant:' . $tenantId), 0, 32);
        $storageKey = 'tenant_' . $tenantKey . '/' . substr($fileKey, 5, 2) . '/' . $fileKey . '.bin';
        $target = $this->path($storageKey, true);
        $directory = dirname($target);
        self::createDirectory($directory);
        $this->assertContainedDirectory($directory);
        if (is_link($target) || file_exists($target)) {
            throw FileMediaException::storageUnavailable();
        }
        $temporary = tempnam($directory, '.upload-');
        if (!is_string($temporary)) {
            throw FileMediaException::storageUnavailable();
        }
        try {
            if (!chmod($temporary, 0600)) {
                throw FileMediaException::storageUnavailable();
            }
            $input = @fopen($sourcePath, 'rb');
            $output = @fopen($temporary, 'wb');
            if (!is_resource($input) || !is_resource($output)) {
                throw FileMediaException::storageUnavailable();
            }
            try {
                $copied = stream_copy_to_stream($input, $output);
                if (!is_int($copied) || $copied < 1 || !fflush($output)) {
                    throw FileMediaException::storageUnavailable();
                }
                if (function_exists('fsync') && !fsync($output)) {
                    throw FileMediaException::storageUnavailable();
                }
            } finally {
                fclose($input);
                fclose($output);
            }
            if (!rename($temporary, $target)) {
                throw FileMediaException::storageUnavailable();
            }
        } catch (Throwable $exception) {
            if (is_file($temporary) || is_link($temporary)) {
                @unlink($temporary);
            }
            if ($exception instanceof FileMediaException) {
                throw $exception;
            }
            throw FileMediaException::storageUnavailable();
        }

        return new StoredObject($this->key(), $storageKey);
    }

    public function open(string $storageKey)
    {
        $path = $this->path($storageKey);
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw FileMediaException::storageUnavailable();
        }
        $stream = @fopen($path, 'rb');
        if (!is_resource($stream)) {
            throw FileMediaException::storageUnavailable();
        }

        return $stream;
    }

    public function remove(string $storageKey): void
    {
        $path = $this->path($storageKey);
        if (is_link($path)) {
            throw FileMediaException::storageUnavailable();
        }
        if (file_exists($path) && (!is_file($path) || !unlink($path))) {
            throw FileMediaException::storageUnavailable();
        }
    }

    private function path(string $storageKey, bool $allowMissingLeaf = false): string
    {
        if (preg_match('/^tenant_[0-9a-f]{32}\/[0-9a-f]{2}\/file_[0-9a-f]{32}\.bin$/D', $storageKey) !== 1) {
            throw FileMediaException::storageUnavailable();
        }
        $segments = explode('/', $storageKey);
        $path = $this->root;
        foreach ($segments as $index => $segment) {
            $path .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($path)) {
                throw FileMediaException::storageUnavailable();
            }
            if (!$allowMissingLeaf && $index < count($segments) - 1 && !is_dir($path)) {
                throw FileMediaException::storageUnavailable();
            }
        }
        if (!self::contains($this->root, $path)) {
            throw FileMediaException::storageUnavailable();
        }

        return $path;
    }

    private function assertContainedDirectory(string $directory): void
    {
        $resolved = realpath($directory);
        if (!is_string($resolved) || !self::contains($this->root, $resolved)) {
            throw FileMediaException::storageUnavailable();
        }
        $relative = ltrim(substr($resolved, strlen($this->root)), DIRECTORY_SEPARATOR);
        $cursor = $this->root;
        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                throw FileMediaException::storageUnavailable();
            }
        }
    }

    private static function createDirectory(string $path): void
    {
        if ((is_dir($path) && !is_link($path)) || @mkdir($path, 0700, true)) {
            return;
        }
        throw FileMediaException::storageUnavailable();
    }

    private static function absolute(string $path): bool
    {
        return $path !== '' && str_starts_with($path, DIRECTORY_SEPARATOR) && !str_contains($path, "\0");
    }

    private static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private static function physical(string $path): string
    {
        $missing = [];
        $cursor = $path;
        while (!file_exists($cursor) && !is_link($cursor)) {
            if ($cursor === DIRECTORY_SEPARATOR) {
                throw FileMediaException::storageUnavailable();
            }
            array_unshift($missing, basename($cursor));
            $cursor = dirname($cursor);
        }
        $resolved = realpath($cursor);
        if (!is_string($resolved) || !is_dir($resolved)) {
            throw FileMediaException::storageUnavailable();
        }

        return self::normalize(
            rtrim($resolved, DIRECTORY_SEPARATOR)
            . ($missing === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $missing)),
        );
    }

    private static function contains(string $root, string $path): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
