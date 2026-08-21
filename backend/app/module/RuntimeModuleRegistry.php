<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use Composer\InstalledVersions;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Package as KernelPackage;

final class RuntimeModuleRegistry
{
    public static function compile(?string $root = null): CompiledModuleRegistry
    {
        $root ??= dirname(__DIR__, 3);
        /** @var array{kernel_version: string, roots: list<string>, frontend_components: list<string>} $config */
        $config = require $root . '/backend/config/modules.php';
        $kernelPath = InstalledVersions::getInstallPath(KernelPackage::NAME);
        if (!is_string($kernelPath) || $kernelPath === '') {
            throw new ModuleException('MODULE_CONTRACT_MISSING', 'Kernel package installation path is unavailable.');
        }

        return (new ModuleRegistryFactory(
            array_map(
                static fn(string $path): string => $root . '/' . ltrim($path, '/'),
                $config['roots'],
            ),
            $config['frontend_components'],
            $config['kernel_version'],
            rtrim($kernelPath, '/') . '/kernel/resources/schemas/module-manifest.schema.json',
        ))->compileAndCheckBoundaries();
    }

    private function __construct() {}
}
