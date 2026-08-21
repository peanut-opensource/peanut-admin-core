<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

final readonly class ModuleRegistryFactory
{
    /**
     * @param list<string> $moduleRoots
     * @param list<string> $frontendComponents
     */
    public function __construct(
        private array $moduleRoots,
        private array $frontendComponents,
        private string $kernelVersion,
        private string $schemaPath,
    ) {}

    public function compile(): CompiledModuleRegistry
    {
        $loader = new ManifestLoader();
        $layout = $this->layout();

        return (new ModuleRegistryCompiler(
            new OpisManifestSchemaValidator($this->schemaPath),
            new ComposerVersionConstraintMatcher(),
            new ReflectionContractInspector(),
            $this->kernelVersion,
            $this->frontendComponents,
            $layout,
            [
                ...KernelSchema::tableNames(),
                ...AuthorizationSchema::tableNames(),
                ...ModuleSchema::tableNames(),
                ...IdempotencySchema::tableNames(),
                ...DataPermissionSchema::tableNames(),
            ],
            ['admin-web', 'platform-web'],
        ))->compile(array_map($loader->load(...), $this->moduleRoots));
    }

    public function compileAndCheckBoundaries(): CompiledModuleRegistry
    {
        $registry = $this->compile();
        (new ModuleBoundaryChecker($registry, $this->layout(), ['pa_']))->check();

        return $registry;
    }

    private function layout(): ModuleHostLayout
    {
        return new ModuleHostLayout(
            'backend/app/Modules',
            'PeanutAdmin\\App\\Modules',
            'frontend/src/modules',
        );
    }
}
