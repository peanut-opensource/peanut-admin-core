<?php

declare(strict_types=1);

namespace PeanutAdmin\InternalStarter\FileMedia;

use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Storage\StorageProvider;

final class FileMediaStorageFactory
{
    public static function fromConfig(string $path): StorageProvider
    {
        $config = require $path;
        if (!is_array($config)
            || ($config['provider'] ?? null) !== 'local-private'
            || !is_string($config['local_root'] ?? null)
            || !is_array($config['public_roots'] ?? null)) {
            throw FileMediaException::storageUnavailable();
        }

        /** @var list<string> $publicRoots */
        $publicRoots = $config['public_roots'];

        return new LocalPrivateStorageProvider($config['local_root'], $publicRoots);
    }

    private function __construct() {}
}
