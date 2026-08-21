<?php

declare(strict_types=1);

use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\InternalStarter\FileMedia\FileMediaStorageFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/peanut-starter-file-media-' . bin2hex(random_bytes(8));
$source = tempnam(sys_get_temp_dir(), 'peanut-starter-upload-');
if (!is_string($source)) {
    throw new RuntimeException('Could not create the starter upload fixture.');
}
file_put_contents($source, "starter private bytes\n");
$original = getenv('FILE_MEDIA_STORAGE_ROOT');
putenv("FILE_MEDIA_STORAGE_ROOT={$root}");

try {
    $storage = FileMediaStorageFactory::fromConfig(dirname(__DIR__) . '/config/file-media.php');
    $fileKey = 'file_' . str_repeat('a', 32);
    $stored = $storage->store(7, $fileKey, $source);
    $stream = $storage->open($stored->storageKey);
    $contents = stream_get_contents($stream);
    fclose($stream);
    if ($storage->key() !== 'local-private'
        || $stored->providerKey !== 'local-private'
        || $contents !== "starter private bytes\n"
        || str_contains($stored->storageKey, (string) $root)) {
        throw new RuntimeException('Starter File/Media adapter did not preserve its private contract.');
    }
    $storage->remove($stored->storageKey);
    try {
        $storage->open('../outside');
        throw new RuntimeException('Starter File/Media adapter accepted a traversal key.');
    } catch (FileMediaException $exception) {
        if ($exception->errorCode !== 'FILE_STORAGE_UNAVAILABLE') {
            throw $exception;
        }
    }
} finally {
    $original === false ? putenv('FILE_MEDIA_STORAGE_ROOT') : putenv("FILE_MEDIA_STORAGE_ROOT={$original}");
    @unlink($source);
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($root);
    }
}

fwrite(STDOUT, "Internal starter File/Media integration: OK\n");
