<?php

declare(strict_types=1);

namespace PeanutAdmin\App\authorization;

use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\DataPermission\Catalog\ResourceOperationCatalog;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Policy\PolicyCache;
use PeanutAdmin\DataPermission\Policy\PolicyRepository;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Application composition for cross-Module data-permission providers. */
final readonly class DataPermissionComposition
{
    public function __construct(
        private ResourceOperationCatalog $catalog,
        private PolicyRepository $policies,
        private PolicyCache $cache,
        private TenantAuthorizationEvaluator $authorization,
    ) {}

    public function engine(?DataPermissionRuntimeRegistry $runtime = null): DataPermissionEngine
    {
        $runtime ??= $this->runtime();

        return new DataPermissionEngine(
            $this->catalog,
            $this->policies,
            $this->cache,
            $this->authorization,
            $runtime->resourceProviders,
            $runtime->targetResolvers,
            $runtime->targetCatalogProviders,
            $runtime->sharedMasterProviders,
        );
    }

    public function runtime(?string $root = null): DataPermissionRuntimeRegistry
    {
        $modules = RuntimeModuleRegistry::compile($root ?? dirname(__DIR__, 3));
        $runtime = new DataPermissionRuntimeRegistry();
        foreach ($modules->modules as $module) {
            $provider = $this->moduleProvider($module);
            if ($provider instanceof DataPermissionModuleProvider) {
                $provider->registerDataPermission($runtime);
            }
        }

        return $runtime;
    }

    private function moduleProvider(ManifestDocument $module): object
    {
        $backend = $module->data['backend'] ?? null;
        $class = is_array($backend) ? ($backend['provider'] ?? null) : null;
        if (!is_string($class) || !class_exists($class)) {
            throw new ModuleException('MODULE_CONTRACT_MISSING', 'Module runtime provider is unavailable.');
        }

        return new $class();
    }
}
