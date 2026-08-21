<?php

declare(strict_types=1);

namespace PeanutAdmin\Examples\ExternalHost;

use PeanutAdmin\Kernel\Authorization\PermissionRequirement;
use PeanutAdmin\Kernel\Host\ExternalHostConfiguration;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PHPUnit\Framework\TestCase;

final class ExampleExternalHostContractTest extends TestCase
{
    public function testFictionalHostOwnsFiveExplicitOperations(): void
    {
        $configuration = new ExternalHostConfiguration(
            new ModuleHostLayout('backend/app/Modules', 'FixtureHost\\App\\Modules', 'frontend/src/modules'),
            ['backend/app/Modules/Fixture/Record'],
            '/api/v1',
            '/api/platform/v1',
            'docs/api/openapi.yaml',
            'backend/route/openapi-generated.php',
            'packages/web/generated/api.d.ts',
            ['fixture-web'],
            'X-Request-ID',
        );

        $operations = [
            $this->operation('fixtureRecordsList', 'GET', '/api/v1/fixture/records', 'read', 'query', 'many_readable'),
            $this->operation('fixtureRecordDetail', 'GET', '/api/v1/fixture/records/{record_id}', 'read', 'targets', 'one_required'),
            $this->operation('fixtureRecordCreate', 'POST', '/api/v1/fixture/records', 'create', 'targets', 'one_required', true),
            $this->operation('fixtureRecordUpdate', 'PATCH', '/api/v1/fixture/records/{record_id}', 'update', 'targets', 'one_required', true),
            $this->operation('fixtureRecordStatus', 'POST', '/api/v1/fixture/records/{record_id}/status', 'status', 'targets', 'one_required', true),
        ];

        foreach ($operations as $operation) {
            $configuration->assertOperation($operation);
        }

        self::assertSame([
            'fixtureRecordsList',
            'fixtureRecordDetail',
            'fixtureRecordCreate',
            'fixtureRecordUpdate',
            'fixtureRecordStatus',
        ], array_map(static fn(ExternalOperationDefinition $operation): string => $operation->operationId, $operations));
    }

    private function operation(
        string $operationId,
        string $method,
        string $path,
        string $permission,
        string $dataMode,
        string $cardinality,
        bool $command = false,
    ): ExternalOperationDefinition {
        return new ExternalOperationDefinition(
            $operationId,
            $method,
            $path,
            'tenant',
            'fixture.record',
            new PermissionRequirement('tenant', ["fixture.record.{$permission}"]),
            'fixture.record',
            $dataMode,
            $cardinality,
            $command,
            $command,
        );
    }
}
