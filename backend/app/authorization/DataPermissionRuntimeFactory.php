<?php

declare(strict_types=1);

namespace PeanutAdmin\App\authorization;

use PDO;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\DataPermission\Catalog\PdoResourceOperationCatalog;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Policy\PdoPolicyRepository;
use PeanutAdmin\DataPermission\Policy\PolicyCache;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;

final class DataPermissionRuntimeFactory
{
    private function __construct() {}

    public static function create(
        PDO $pdo,
        ?string $root = null,
        ?DataPermissionRuntimeRegistry $runtime = null,
    ): DataPermissionEngine {
        $runtime ??= self::runtime($pdo, $root);

        return new DataPermissionEngine(
            new PdoResourceOperationCatalog($pdo),
            new PdoPolicyRepository($pdo),
            new PolicyCache(),
            new TenantAuthorizationEvaluator(
                new PdoTenantAuthorizationRepository($pdo),
                new RevisionPermissionCache(),
            ),
            $runtime->resourceProviders,
            $runtime->targetResolvers,
            $runtime->targetCatalogProviders,
            $runtime->sharedMasterProviders,
        );
    }

    public static function runtime(PDO $pdo, ?string $root = null): DataPermissionRuntimeRegistry
    {
        $root ??= dirname(__DIR__, 3);
        $modules = RuntimeModuleRegistry::compile($root);
        $runtime = new DataPermissionRuntimeRegistry();
        foreach ($modules->modules as $module) {
            $provider = self::moduleProvider($module);
            if ($provider instanceof DataPermissionModuleProvider) {
                $provider->registerDataPermission($runtime, $pdo);
            }
        }

        return $runtime;
    }

    private static function moduleProvider(ManifestDocument $module): object
    {
        $backend = $module->data['backend'] ?? null;
        $class = is_array($backend) ? ($backend['provider'] ?? null) : null;
        if (!is_string($class) || !class_exists($class)) {
            throw new ModuleException('MODULE_CONTRACT_MISSING', 'Module runtime provider is unavailable.');
        }

        return new $class();
    }
}
