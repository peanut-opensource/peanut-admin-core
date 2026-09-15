<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use RuntimeException;
use think\facade\Db;

final readonly class InstallWorkflow
{
    public function __construct(private string $root) {}

    /**
     * @param array{code: string, name: string, owner_email: string, owner_name: string, owner_password?: string}|null $tenant
     * @return array<string, mixed>
     */
    public function run(
        InstallProductProfile $profile,
        string $email,
        string $password,
        string $displayName,
        ?array $tenant = null,
        bool $allowExisting = false,
    ): array {
        (new InstallEnvironmentChecker($this->root))->assertReady();
        $upgradeWorkflow = new UpgradeWorkflow($this->root);
        $existingSchema = $this->tableExists('pa_platform_operator');
        $upgrade = $allowExisting && $existingSchema
            ? $upgradeWorkflow->assertCurrentReleaseNoop()
            : $upgradeWorkflow->installEmptyDatabase();

        $operatorCount = Db::name('platform_operator')->count();
        if ($existingSchema && $operatorCount === 0) {
            throw new RuntimeException('INSTALL_INCOMPLETE: existing schema has no platform owner.');
        }
        if ($operatorCount !== 0) {
            if (!$allowExisting) {
                throw new RuntimeException('INSTALL_ALREADY_COMPLETED: use --allow-existing for an idempotent check.');
            }

            return [
                'status' => 'already-installed',
                'profile' => $profile->key,
                'upgrade' => $upgrade,
            ];
        }

        if ($email === '' || $password === '' || $displayName === '') {
            throw new RuntimeException('INSTALL_BOOTSTRAP_INPUT_INVALID: email, password, and display name are required.');
        }
        $bootstrap = $this->bootstrapService();
        $platform = $bootstrap->bootstrapPlatformOwner(
            $email,
            $password,
            $displayName,
            'install-' . bin2hex(random_bytes(12)),
        );

        $tenantResult = null;
        if ($tenant !== null) {
            foreach (['code', 'name', 'owner_email', 'owner_name'] as $required) {
                if (trim($tenant[$required]) === '') {
                    throw new RuntimeException("INSTALL_TENANT_INPUT_INVALID: {$required} is required.");
                }
            }
            $sameCredential = strtolower(trim($tenant['owner_email'])) === strtolower(trim($email));
            $ownerPassword = $sameCredential ? null : ($tenant['owner_password'] ?? null);
            if (!$sameCredential && (!is_string($ownerPassword) || $ownerPassword === '')) {
                throw new RuntimeException('INSTALL_TENANT_INPUT_INVALID: owner_password is required for a new account.');
            }
            $candidate = $bootstrap->provisionTenantOwnerCandidate(
                $platform->operatorId,
                $tenant['code'],
                $tenant['name'],
                $tenant['owner_email'],
                $ownerPassword,
                $tenant['owner_name'],
                'install-tenant-' . bin2hex(random_bytes(12)),
            );
            $bootstrap->activateTenantOwner(
                $platform->operatorId,
                $candidate->tenantId,
                $candidate->memberId,
                'install-owner-activate-' . bin2hex(random_bytes(12)),
            );
            $bootstrap->activateTenant(
                $platform->operatorId,
                $candidate->tenantId,
                'install-tenant-activate-' . bin2hex(random_bytes(12)),
            );
            $appliedProfile = (new InstallProductProfileApplier($this->root))
                ->apply($candidate->tenantId, $profile);
            $tenantResult = [
                'tenant_id' => $candidate->tenantId,
                'owner_member_id' => $candidate->memberId,
                'profile' => $appliedProfile,
            ];
        }

        return [
            'status' => 'installed',
            'profile' => $profile->key,
            'platform' => $platform->toArray(),
            'tenant' => $tenantResult,
            'upgrade' => $upgrade,
        ];
    }

    private function tableExists(string $table): bool
    {
        return Db::table('information_schema.tables')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->count() === 1;
    }

    private function bootstrapService(): BootstrapService
    {
        return new BootstrapService();
    }
}
