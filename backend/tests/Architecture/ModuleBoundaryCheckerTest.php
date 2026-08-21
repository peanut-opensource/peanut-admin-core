<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Architecture;

use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModuleBoundaryCheckerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/peanut-module-boundary-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testOtherModuleInternalsAndTablesFailTheBuild(): void
    {
        file_put_contents($this->root . '/Bad.php', <<<'PHP'
<?php
use PeanutAdmin\App\Modules\Example\Other\Infrastructure\OtherRepository;
$sql = 'SELECT * FROM pa_example_other';
PHP);
        $manifest = ManifestDocument::fromArray($this->root, ['key' => 'example.owner']);
        $registry = new CompiledModuleRegistry(
            [$manifest],
            [],
            ['pa_example_owner' => 'example.owner', 'pa_example_other' => 'example.other'],
            [],
            'revision',
        );

        $this->expectException(ModuleException::class);
        $this->checker($registry)->check();
    }

    public function testPublicContractsAndOwnedTablesPass(): void
    {
        self::expectNotToPerformAssertions();
        $ownerRoot = $this->moduleRoot('owner');
        $otherRoot = $this->moduleRoot('other');
        file_put_contents($ownerRoot . '/Good.php', <<<'PHP'
<?php
use PeanutAdmin\App\Modules\Example\Other\Contracts\OtherQuery;
$sql = 'SELECT * FROM pa_example_owner';
PHP);
        $owner = ManifestDocument::fromArray($ownerRoot, [
            'key' => 'example.owner',
            'dependencies' => [['module_key' => 'example.other', 'version' => '^1.0']],
            'contracts' => ['exports' => []],
        ]);
        $other = ManifestDocument::fromArray($otherRoot, [
            'key' => 'example.other',
            'dependencies' => [],
            'contracts' => ['exports' => [
                'PeanutAdmin\\App\\Modules\\Example\\Other\\Contracts\\OtherQuery',
            ]],
        ]);
        $registry = new CompiledModuleRegistry(
            [$other, $owner],
            [],
            ['pa_example_owner' => 'example.owner'],
            [],
            'revision',
        );

        $this->checker($registry)->check();
    }

    public function testContractImportRequiresDeclaredDependency(): void
    {
        [$owner, $other] = $this->contractFixture([], [
            'PeanutAdmin\\App\\Modules\\Example\\Other\\Contracts\\OtherQuery',
        ]);

        $this->expectModuleCode('MODULE_DEPENDENCY_MISSING', function () use ($other, $owner): void {
            $this->checker(new CompiledModuleRegistry([$other, $owner], [], [], [], 'revision'))->check();
        });
    }

    public function testContractImportMustBeExplicitlyExported(): void
    {
        [$owner, $other] = $this->contractFixture(['example.other'], []);

        $this->expectModuleCode('MODULE_CONTRACT_MISSING', function () use ($other, $owner): void {
            $this->checker(new CompiledModuleRegistry([$other, $owner], [], [], [], 'revision'))->check();
        });
    }

    public function testNowdocCrossTableQueryIsRejectedButExplicitDatabaseForeignKeyIsAllowed(): void
    {
        mkdir($this->root . '/Database');
        file_put_contents($this->root . '/Database/Schema.php', <<<'PHP'
<?php
$sql = <<<'SQL'
CREATE TABLE `pa_example_owner` (
  `other_id` BIGINT UNSIGNED NOT NULL,
  CONSTRAINT `fk_other` FOREIGN KEY (`other_id`) REFERENCES `pa_example_other` (`id`)
)
SQL;
PHP);
        $manifest = ManifestDocument::fromArray($this->root, ['key' => 'example.owner']);
        $registry = new CompiledModuleRegistry(
            [$manifest],
            [],
            ['pa_example_owner' => 'example.owner', 'pa_example_other' => 'example.other'],
            [],
            'revision',
        );
        $this->checker($registry)->check();

        file_put_contents($this->root . '/Bad.php', <<<'PHP'
<?php
$sql = <<<'SQL'
SELECT * FROM pa_example_other
SQL;
PHP);
        $this->expectException(ModuleException::class);
        $this->checker($registry)->check();
    }

    public function testExternalNamespaceAndManagedBusinessPrefixAreEnforced(): void
    {
        file_put_contents($this->root . '/Bad.php', <<<'PHP'
<?php
use Dcs\App\Modules\Dcs\Other\Infrastructure\OtherRepository;
$sql = 'SELECT * FROM dcs_other';
PHP);
        $manifest = ManifestDocument::fromArray($this->root, ['key' => 'dcs.owner']);
        $registry = new CompiledModuleRegistry(
            [$manifest],
            [],
            ['dcs_owner' => 'dcs.owner', 'dcs_other' => 'dcs.other'],
            [],
            'revision',
        );
        $layout = new ModuleHostLayout('backend/app/Modules', 'Dcs\\App\\Modules', 'frontend/src/modules');

        $this->expectException(ModuleException::class);
        (new ModuleBoundaryChecker($registry, $layout, ['pa_', 'dcs_']))->check();
    }

    public function testUnregisteredModuleNamespaceIsRejectedWithoutTableReference(): void
    {
        file_put_contents($this->root . '/Bad.php', <<<'PHP'
<?php
use PeanutAdmin\App\Modules\Unregistered\Infrastructure\Repository;
PHP);
        $manifest = ManifestDocument::fromArray($this->root, ['key' => 'example.owner']);
        $registry = new CompiledModuleRegistry([$manifest], [], [], [], 'revision');

        $this->expectModuleCode('MODULE_REGISTRY_CONFLICT', function () use ($registry): void {
            $this->checker($registry)->check();
        });
    }

    /**
     * @param list<string> $dependencies
     * @param list<string> $exports
     * @return array{ManifestDocument, ManifestDocument}
     */
    private function contractFixture(array $dependencies, array $exports): array
    {
        $ownerRoot = $this->moduleRoot('contract-owner-' . count($dependencies));
        $otherRoot = $this->moduleRoot('contract-other-' . count($exports));
        file_put_contents($ownerRoot . '/Consumer.php', <<<'PHP'
<?php
use PeanutAdmin\App\Modules\Example\Other\Contracts\OtherQuery;
PHP);

        return [
            ManifestDocument::fromArray($ownerRoot, [
                'key' => 'example.owner',
                'dependencies' => array_map(
                    static fn(string $key): array => ['module_key' => $key, 'version' => '^1.0'],
                    $dependencies,
                ),
                'contracts' => ['exports' => []],
            ]),
            ManifestDocument::fromArray($otherRoot, [
                'key' => 'example.other',
                'dependencies' => [],
                'contracts' => ['exports' => $exports],
            ]),
        ];
    }

    private function moduleRoot(string $name): string
    {
        $path = $this->root . '/' . $name;
        mkdir($path, 0777, true);

        return $path;
    }

    private function checker(CompiledModuleRegistry $registry): ModuleBoundaryChecker
    {
        return new ModuleBoundaryChecker(
            $registry,
            new ModuleHostLayout(
                'backend/app/Modules',
                'PeanutAdmin\\App\\Modules',
                'frontend/src/modules',
            ),
            ['pa_'],
        );
    }

    private function expectModuleCode(string $errorCode, callable $operation): void
    {
        try {
            $operation();
        } catch (ModuleException $exception) {
            self::assertSame($errorCode, $exception->errorCode);

            return;
        }

        self::fail("Expected {$errorCode}.");
    }
}
