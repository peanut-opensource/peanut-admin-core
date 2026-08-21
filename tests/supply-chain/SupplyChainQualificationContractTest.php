<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplyChainQualificationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSupplyChainGateOwnsAuditsSecretsAndLicenseInventory(): void
    {
        foreach (['scripts/check-supply-chain', 'scripts/check-secrets'] as $relativePath) {
            self::assertFileExists($this->root . '/' . $relativePath, $relativePath);
            self::assertTrue(is_executable($this->root . '/' . $relativePath), $relativePath);
        }

        $gate = (string) file_get_contents($this->root . '/scripts/check-supply-chain');
        self::assertStringContainsString('composer audit', $gate);
        self::assertStringContainsString('pnpm audit', $gate);
        self::assertStringContainsString('./scripts/check-secrets', $gate);
        self::assertStringContainsString('./scripts/check-third-party-licenses', $gate);
        self::assertStringContainsString(
            'third-party-licenses.generated.md',
            (string) file_get_contents($this->root . '/scripts/check-third-party-licenses'),
        );
        $licenseGate = (string) file_get_contents($this->root . '/scripts/check-third-party-licenses');
        self::assertStringContainsString('composer.lock', $licenseGate);
        self::assertStringNotContainsString("['composer', 'licenses'", $licenseGate);
        self::assertStringContainsString(
            './scripts/check-supply-chain',
            (string) file_get_contents($this->root . '/scripts/check'),
        );
    }

    public function testLicenseInventoryIsGeneratedAndPortable(): void
    {
        $path = $this->root . '/docs/reference/third-party-licenses.generated.md';
        self::assertFileExists($path);
        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('GENERATED FILE', $contents);
        self::assertStringNotContainsString('/Users/', $contents);
        self::assertFileExists($this->root . '/.github/workflows/security.yml');
    }
}
