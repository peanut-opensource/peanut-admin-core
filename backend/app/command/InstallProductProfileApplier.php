<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use Composer\InstalledVersions;
use DateTimeImmutable;
use PeanutAdmin\App\module\ModuleRegistryFactory;
use PeanutAdmin\App\module\OpisTenantModuleConfigValidator;
use PeanutAdmin\App\referencecode\ReferenceCodeHttpService;
use PeanutAdmin\App\referencecode\PreBootstrapReferenceCodeDefinitionSynchronizer;
use PeanutAdmin\App\setting\PreBootstrapSettingDefinitionSynchronizer;
use PeanutAdmin\App\setting\SettingDefinitionCatalog;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\ThinkPhpModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Package as KernelPackage;
use think\facade\Db;

final readonly class InstallProductProfileApplier
{
    public function __construct(private string $root) {}

    /** @return array{enabled_modules: list<string>, role_templates: list<string>, default_department_id: int|null} */
    public function apply(int $tenantId, InstallProductProfile $profile): array
    {
        /** @var array{kernel_version: string, roots: list<string>, frontend_components: list<string>} $config */
        $config = require $this->root . '/backend/config/modules.php';
        $registry = (new ModuleRegistryFactory(
            array_map(fn(string $path): string => $this->root . '/' . ltrim($path, '/'), $config['roots']),
            $config['frontend_components'],
            $config['kernel_version'],
            $this->kernelPath() . '/resources/schemas/module-manifest.schema.json',
        ))->compileAndCheckBoundaries();
        $profileKeys = $profile->moduleKeys();
        $unknown = array_values(array_diff($profileKeys, $registry->moduleKeys()));
        if ($unknown !== []) {
            throw new ModuleException('MODULE_NOT_INSTALLED', 'Profile references unknown module: ' . $unknown[0]);
        }
        (new PreBootstrapSettingDefinitionSynchronizer())->synchronize(
            (new SettingDefinitionCatalog())->fromModules($registry),
            new DateTimeImmutable('now'),
        );
        (new PreBootstrapReferenceCodeDefinitionSynchronizer())->synchronize(
            ReferenceCodeHttpService::definitionRegistry($registry),
            new DateTimeImmutable('now'),
        );

        $manager = new TenantModuleManager(
            $registry,
            new ThinkPhpModuleRuntimeRepository(),
            new OpisTenantModuleConfigValidator(),
        );
        $enabled = [];
        $now = new DateTimeImmutable('now');
        foreach ($registry->modules as $module) {
            $moduleKey = (string) $module->data['key'];
            if (!in_array($moduleKey, $profileKeys, true)) {
                continue;
            }
            $manager->enable(
                $tenantId,
                $moduleKey,
                $profile->moduleConfig($moduleKey),
                $now,
                'product_profile',
            );
            $enabled[] = $moduleKey;
        }

        return [
            'enabled_modules' => $enabled,
            'role_templates' => $profile->roleTemplates,
            'default_department_id' => $this->createDefaultDepartment($tenantId, $profile->defaultDepartment),
        ];
    }

    /** @param array{code: string, name: string}|null $department */
    private function createDefaultDepartment(int $tenantId, ?array $department): ?int
    {
        if ($department === null) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s.000');
        $query = Db::name('department')
            ->where('tenant_id', $tenantId)
            ->where('code', $department['code']);
        $id = $query->value('id');
        if ($id === null) {
            $id = Db::name('department')->insertGetId([
                'tenant_id' => $tenantId,
                'code' => $department['code'],
                'name' => $department['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return (int)$id;
    }

    private function kernelPath(): string
    {
        $path = InstalledVersions::getInstallPath(KernelPackage::NAME);
        if (!is_string($path) || $path === '') {
            throw new \RuntimeException('PACKAGE_INSTALL_PATH_UNAVAILABLE: peanut-admin/core.');
        }

        return rtrim($path, '/') . '/kernel';
    }
}
