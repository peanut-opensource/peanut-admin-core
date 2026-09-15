<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Security;

use PeanutAdmin\App\referencecode\ReferenceCodeHttpService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeException;
use PHPUnit\Framework\TestCase;

final class ReferenceCodeSecurityTest extends TestCase
{
    public function testHostUsesInjectedCompositionAndNativeReferenceCodePersistence(): void
    {
        $service = $this->source('backend/app/referencecode/ReferenceCodeHttpService.php');
        $store = $this->source('packages/php/reference-codes/src/Persistence/ReferenceCodeStore.php');
        $controller = $this->source('backend/app/controller/api/v1/ReferenceCodeController.php');

        self::assertStringContainsString('private ExternalOperationHost $host', $service);
        self::assertStringContainsString('$this->host->command(', $service);
        self::assertStringContainsString('Db::transaction(', $store);
        self::assertStringNotContainsString('AtomicOperationAdapter', $service . $store);
        self::assertStringNotContainsString('ThinkPhpTransactionManager', $service . $store);
        self::assertStringNotContainsString('PdoReferenceCodeRepository', $service . $store);
        self::assertStringNotContainsString('PDO', $service . $controller);
        self::assertStringNotContainsString('PDO', $controller);
     }

    public function testAllOperationsAreCurrentTenantOnlyAndAcceptNoTypedTargets(): void
    {
        foreach (ReferenceCodeHttpService::operations() as $operation) {
            self::assertSame('tenant', $operation->audience);
            self::assertSame('none', $operation->dataAuthorization);
            self::assertSame('none', $operation->targetCardinality);
            self::assertNull($operation->resourceKey);
        }
    }

    public function testCommandGuardLocksHostOwnerAndCurrentDefinitionBeforeIdempotency(): void
    {
        $service = $this->source('backend/app/referencecode/ReferenceCodeHttpService.php');
        $guard = $this->functionSource($service, 'commandGuard');
        $availability = $this->functionSource($service, 'assertModuleAvailable');
        $locks = $this->functionSource($service, 'lockModuleAvailability');

        self::assertStringContainsString('guard: $this->commandGuard(', $service);
        self::assertStringNotContainsString('lockAvailabilityReads:', $service);
        self::assertStringContainsString("'peanut.reference-codes'", $guard);
        self::assertStringContainsString('assertCurrentDefinition($definition, true)', $guard);
        self::assertSame(2, substr_count($guard, "true,\n"));
        self::assertStringContainsString(
            'self::lockModuleAvailability($context->tenantId, $moduleKey);',
            $availability,
        );
        self::assertStringContainsString('$this->availability->assertAvailable(', $availability);
        self::assertSame(2, substr_count($locks, 'FOR SHARE'));
        self::assertStringContainsString("Db::name('module_installation')", $locks);
        self::assertStringContainsString("Db::name('tenant_module')", $locks);
    }

    public function testEveryDomainCommandRepeatsTheDefinitionAndOwnerCheck(): void
    {
        $service = $this->source('backend/app/referencecode/ReferenceCodeHttpService.php');

        self::assertGreaterThanOrEqual(5, substr_count($service, 'definitionRegistry($modules)->require('));
        self::assertGreaterThanOrEqual(3, substr_count($service, '$this->assertOwnerAvailable('));
        self::assertSame(3, substr_count($service, '$this->admin->'));
    }

    public function testRequestInputsCannotSupplyTenantMemberOwnerPermissionOrTarget(): void
    {
        $valid = [
            'code' => 'sample-code',
            'label' => 'Sample label',
            'metadata' => [],
            'status' => 'active',
            'sort_order' => 0,
            'effective_at' => '2020-01-01T00:00:00.000Z',
            'expires_at' => null,
        ];
        foreach (['tenant_id', 'member_id', 'module_key', 'permission', 'target_id'] as $forbidden) {
            $this->expectInvalid(static fn() => ReferenceCodeHttpService::versionInput(
                $valid + [$forbidden => 1],
                true,
            ));
        }
    }

    public function testAuditMetadataIsFixedAndCannotContainLabelOrMetadataValues(): void
    {
        $factory = $this->source('backend/app/referencecode/ReferenceCodeHttpService.php');

        self::assertMatchesRegularExpression(
            '/auditMetadata\(.*?return \$entry->auditMetadata\(\$changedFields\);/s',
            $factory,
        );
        self::assertStringNotContainsString("'label' =>", $this->functionSource($factory, 'auditMetadata'));
        self::assertStringNotContainsString("'metadata' =>", $this->functionSource($factory, 'auditMetadata'));
        self::assertStringNotContainsString("'tenant_id' =>", $this->functionSource($factory, 'auditMetadata'));
        self::assertStringNotContainsString("'member_id' =>", $this->functionSource($factory, 'auditMetadata'));
    }

    public function testProblemMappingUsesStableRedactedCodesAndMessages(): void
    {
        $factory = $this->source('backend/app/referencecode/ReferenceCodeHttpService.php');
        foreach ([
            'REFERENCE_CODE_SET_NOT_FOUND',
            'REFERENCE_CODE_NOT_FOUND',
            'REFERENCE_CODE_RETIRED',
            'REFERENCE_CODE_ALREADY_EXISTS',
            'REFERENCE_CODE_REVISION_MISMATCH',
            'REFERENCE_CODE_REQUEST_INVALID',
            'REFERENCE_CODE_METADATA_INVALID',
            'REFERENCE_CODE_INTERVAL_INVALID',
            'PRECONDITION_REQUIRED',
            'INTERNAL_ERROR',
        ] as $code) {
            self::assertStringContainsString($code, $factory);
        }
        self::assertStringNotContainsString('getTrace', $factory);
        self::assertStringNotContainsString('getMessage()', $this->functionSource($factory, 'apiException'));
    }

    public function testModuleOwnsOnlyInfrastructureTablesAndNoCommittedValues(): void
    {
        $module = json_decode($this->source('backend/app/modules/peanut/reference_codes/module.json'), true, 32, JSON_THROW_ON_ERROR);
        $definitions = json_decode(
            $this->source('backend/app/modules/peanut/reference_codes/resources/reference-code-sets.json'),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('peanut.reference-codes', $module['key']);
        self::assertSame([
            'pa_reference_code_set',
            'pa_reference_code_entry',
            'pa_reference_code_entry_version',
        ], $module['database']['owned_tables']);
        self::assertSame([], $definitions);
    }

    public function testProtectedResourceIsTenantOwnedAndEveryOperationIsTargetFree(): void
    {
        $resources = json_decode(
            $this->source('backend/app/modules/peanut/reference_codes/resources/protected-resources.json'),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $resources);
        self::assertSame('peanut.reference-code', $resources[0]['key']);
        self::assertSame('tenant_owned', $resources[0]['ownership']);
        self::assertSame(
            'PeanutAdmin\\App\\modules\\peanut\\reference_codes\\ModuleProvider',
            $resources[0]['provider'],
        );
        self::assertArrayNotHasKey('scope_provider', $resources[0]);
        foreach ($resources[0]['operations'] as $operation) {
            self::assertSame('tenant_wide', $operation['access_mode']);
            self::assertSame('none', $operation['target_cardinality']);
            self::assertSame([], $operation['target_types']);
            self::assertSame([], $operation['conditions']);
        }
    }

    public function testHostSliceContainsNoProductVocabularySecretsOrPrivatePaths(): void
    {
        $source = '';
        foreach ($this->hostFiles() as $file) {
            $source .= "\n" . $this->source($file);
        }
        foreach ([
            'order status',
            'settlement',
            'inventory',
            'taxonom',
            'approval step',
            'customer category',
            '/Users/',
            'BEGIN PRIVATE KEY',
            'password=',
            'token=',
        ] as $forbidden) {
            self::assertStringNotContainsString(strtolower($forbidden), strtolower($source));
        }
    }

    private function expectInvalid(callable $operation): void
    {
        try {
            $operation();
        } catch (ReferenceCodeException $exception) {
            self::assertSame('REFERENCE_CODE_REQUEST_INVALID', $exception->errorCode);
            self::assertSame(422, $exception->httpStatus);

            return;
        }
        self::fail('Expected the reference-code request to fail closed.');
    }

    private function functionSource(string $source, string $name): string
    {
        $start = strpos($source, "function {$name}(");
        self::assertNotFalse($start);
        $next = strpos($source, "\n    }", (int) $start);
        self::assertNotFalse($next);

        return substr($source, (int) $start, (int) $next - (int) $start + 6);
    }

    /** @return list<string> */
    private function hostFiles(): array
    {
        return [
            'backend/app/modules/peanut/reference_codes/module.json',
            'backend/app/modules/peanut/reference_codes/ModuleProvider.php',
            'backend/app/modules/peanut/reference_codes/database/migrations/20260719040101_create_reference_code_sets.php',
            'backend/app/modules/peanut/reference_codes/database/migrations/20260719040102_create_reference_code_entries.php',
            'backend/app/modules/peanut/reference_codes/database/migrations/20260719040103_create_reference_code_entry_versions.php',
            'backend/app/modules/peanut/reference_codes/resources/menus.json',
            'backend/app/modules/peanut/reference_codes/resources/permissions.json',
            'backend/app/modules/peanut/reference_codes/resources/protected-resources.json',
            'backend/app/modules/peanut/reference_codes/resources/reference-code-sets.json',
            'backend/app/controller/api/v1/ReferenceCodeController.php',
            'backend/app/referencecode/ReferenceCodeHttpService.php',
            'docs/api/schemas/reference-codes.yaml',
        ];
    }

    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
