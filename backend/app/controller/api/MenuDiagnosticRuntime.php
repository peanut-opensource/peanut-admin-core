<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api;

use PeanutAdmin\Kernel\Menu\MenuDefinition;

final class MenuDiagnosticRuntime
{
    private function __construct() {}

    /**
     * @param list<MenuDefinition> $definitions
     * @param callable(string): bool $deploymentAvailable
     * @param callable(string): bool $tenantAvailable
     * @param callable(string): bool $permissionGranted
     * @return list<array<string, mixed>>
     */
    public static function explain(
        array $definitions,
        string $audience,
        string $clientKey,
        callable $deploymentAvailable,
        callable $tenantAvailable,
        callable $permissionGranted,
    ): array {
        $byKey = [];
        foreach ($definitions as $definition) {
            $reason = match (true) {
                $definition->scope !== $audience => 'audience_mismatch',
                !in_array($clientKey, $definition->clientKeys, true) => 'client_unavailable',
                $definition->moduleKey !== 'core' && !$deploymentAvailable($definition->moduleKey) => 'deployment_module_unavailable',
                $definition->moduleKey !== 'core' && $audience === 'tenant' && !$tenantAvailable($definition->moduleKey) => 'tenant_module_disabled',
                $definition->requiredPermission !== null && !$permissionGranted($definition->requiredPermission) => 'permission_not_granted',
                default => 'visible',
            };
            $byKey[$definition->key] = [
                'key' => $definition->key,
                'module_key' => $definition->moduleKey,
                'type' => $definition->type,
                'name' => $definition->name,
                'route_name' => $definition->routeName,
                'route_path' => $definition->routePath,
                'component_key' => $definition->componentKey,
                'required_permission' => $definition->requiredPermission,
                'client_keys' => $definition->clientKeys,
                'icon' => $definition->icon,
                'visible' => $reason === 'visible',
                'reason' => $reason,
                'trusted_route_path' => $definition->routePath,
                '_parent_key' => $definition->parentKey,
            ];
        }

        do {
            $changed = false;
            foreach ($definitions as $definition) {
                $item = $byKey[$definition->key];
                if (!$item['visible']) {
                    continue;
                }
                if ($definition->parentKey !== null && !($byKey[$definition->parentKey]['visible'] ?? false)) {
                    $byKey[$definition->key]['visible'] = false;
                    $byKey[$definition->key]['reason'] = 'parent_hidden';
                    $changed = true;
                    continue;
                }
                if ($definition->type === 'group') {
                    $hasVisibleChild = false;
                    foreach ($byKey as $candidate) {
                        if (($candidate['_parent_key'] ?? null) === $definition->key && $candidate['visible']) {
                            $hasVisibleChild = true;
                            break;
                        }
                    }
                    if (!$hasVisibleChild) {
                        $byKey[$definition->key]['visible'] = false;
                        $byKey[$definition->key]['reason'] = 'empty_group';
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        foreach ($byKey as &$item) {
            unset($item['_parent_key']);
        }
        unset($item);

        return array_values($byKey);
    }
}
