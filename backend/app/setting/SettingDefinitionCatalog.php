<?php

declare(strict_types=1);

namespace PeanutAdmin\App\setting;

use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;

final readonly class SettingDefinitionCatalog
{
    public function registry(): SettingDefinitionRegistry
    {
        return $this->fromModules(RuntimeModuleRegistry::compile());
    }

    public function fromModules(CompiledModuleRegistry $modules): SettingDefinitionRegistry
    {
        $registry = new SettingDefinitionRegistry();
        $loader = new SettingDefinitionLoader();
        foreach ($modules->modules as $module) {
            $moduleKey = $this->moduleKey($module);
            $relativePath = $this->definitionPath($module);
            $definitions = $relativePath === null
                ? []
                : $loader->load(
                    $moduleKey,
                    $this->ownedResourcePath($module, $relativePath),
                    $this->targetDeclarations($module),
                );
            $registry->registerModule($moduleKey, $definitions);
        }

        return $registry;
    }

    private function moduleKey(ManifestDocument $module): string
    {
        $moduleKey = $module->data['key'] ?? null;
        if (!is_string($moduleKey) || $moduleKey === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition owner is invalid.');
        }

        return $moduleKey;
    }

    private function definitionPath(ManifestDocument $module): ?string
    {
        $backend = $module->data['backend'] ?? null;
        if (!is_array($backend)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition backend metadata is invalid.');
        }
        $path = $backend['setting_definitions'] ?? null;
        if ($path !== null && (!is_string($path) || $path === '')) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition path is invalid.');
        }

        return $path;
    }

    private function ownedResourcePath(ManifestDocument $module, string $relativePath): string
    {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition path is unsafe.');
        }
        $path = realpath($module->root . '/' . $relativePath);
        if ($path === false || !is_file($path) || !str_starts_with($path, $module->root . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings definition resource is outside its Module.');
        }

        return $path;
    }

    /** @return list<array{module_key:string,resource_key:string,operation:string,target_cardinality:string}> */
    private function targetDeclarations(ManifestDocument $module): array
    {
        $moduleKey = $this->moduleKey($module);
        $catalog = $module->data['catalog'] ?? null;
        $resources = is_array($catalog) ? ($catalog['protected_resources'] ?? []) : null;
        if (!is_array($resources) || !array_is_list($resources)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target resources are invalid.');
        }
        $declarations = [];
        foreach ($resources as $resource) {
            $resourceKey = is_array($resource) ? ($resource['key'] ?? null) : null;
            $operations = is_array($resource) ? ($resource['operations'] ?? null) : null;
            if (!is_string($resourceKey) || $resourceKey === '' || !is_array($operations)) {
                throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target declaration is invalid.');
            }
            foreach ($operations as $operation) {
                $operationKey = is_array($operation) ? ($operation['key'] ?? null) : null;
                $cardinality = is_array($operation) ? ($operation['target_cardinality'] ?? null) : null;
                if (!is_string($operationKey) || $operationKey === '' || !is_string($cardinality)) {
                    throw new ModuleException('MODULE_MANIFEST_INVALID', 'Settings target operation metadata is invalid.');
                }
                $declarations[] = [
                    'module_key' => $moduleKey,
                    'resource_key' => $resourceKey,
                    'operation' => $operationKey,
                    'target_cardinality' => $cardinality,
                ];
            }
        }

        return $declarations;
    }
}
