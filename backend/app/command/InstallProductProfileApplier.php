<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use Composer\InstalledVersions;
use DateTimeImmutable;
use PDO;
use PeanutAdmin\App\module\ModuleRegistryFactory;
use PeanutAdmin\App\module\OpisTenantModuleConfigValidator;
use PeanutAdmin\App\referencecode\ReferenceCodeRuntimeFactory;
use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Package as KernelPackage;

final readonly class InstallProductProfileApplier
{
    public function __construct(
        private string $root,
        private PDO $pdo,
    ) {}

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
        SettingsRuntimeFactory::synchronizeDefinitions($this->pdo, $registry, new DateTimeImmutable('now'));
        ReferenceCodeRuntimeFactory::synchronizeDefinitions($this->pdo, $registry, new DateTimeImmutable('now'));

        $manager = new TenantModuleManager(
            $registry,
            new PdoModuleRuntimeRepository($this->pdo),
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
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_department (tenant_id, code, name, created_at, updated_at)
VALUES (:tenant_id, :code, :name, :created_at, :updated_at)
SQL);
        $statement->execute([
            'tenant_id' => $tenantId,
            'code' => $department['code'],
            'name' => $department['name'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $query = $this->pdo->prepare(<<<'SQL'
SELECT id FROM pa_department WHERE tenant_id = :tenant_id AND code = :code
SQL);
        $query->execute(['tenant_id' => $tenantId, 'code' => $department['code']]);
        $id = $query->fetchColumn();

        return $id === false ? null : (int) $id;
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
