<?php

declare(strict_types=1);

namespace PeanutAdmin\InternalStarter\Module;

use Composer\InstalledVersions;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Package as KernelPackage;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use RuntimeException;

final readonly class ModuleRegistryFactory
{
    public function __construct(private string $root) {}

    public function compile(): CompiledModuleRegistry
    {
        $layout = new ModuleHostLayout(
            'backend/src/Modules',
            'ExampleHost\\App\\Modules',
            'frontend/src/modules',
        );
        /** @var array{kernel_version: string, roots: list<string>, frontend_components: list<string>, registered_client_keys: list<string>} $config */
        $config = require $this->root . '/backend/config/modules.php';
        $kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
        if (!is_string($kernelRoot) || $kernelRoot === '') {
            throw new RuntimeException('Installed Kernel package path is unavailable.');
        }
        $loader = new ManifestLoader();
        $documents = array_map(
            fn(string $path) => $loader->load($this->root . '/' . ltrim($path, '/')),
            $config['roots'],
        );
        $registry = (new ModuleRegistryCompiler(
            new OpisManifestSchemaValidator($kernelRoot . '/kernel/resources/schemas/module-manifest.schema.json'),
            new ComposerVersionConstraintMatcher(),
            new ReflectionContractInspector(),
            $config['kernel_version'],
            $config['frontend_components'],
            $layout,
            [
                ...KernelSchema::tableNames(),
                ...AuthorizationSchema::tableNames(),
                ...ModuleSchema::tableNames(),
                ...IdempotencySchema::tableNames(),
                ...DataPermissionSchema::tableNames(),
            ],
            $config['registered_client_keys'],
        ))->compile($documents);
        (new ModuleBoundaryChecker($registry, $layout, ['pa_', 'starter_']))->check();

        return $registry;
    }
}
