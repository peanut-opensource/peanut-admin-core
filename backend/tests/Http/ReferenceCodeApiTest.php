<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Http;

use DateTimeImmutable;
use PeanutAdmin\App\controller\api\v1\ReferenceCodeController;
use PeanutAdmin\App\referencecode\ReferenceCodeRuntimeFactory;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\ReferenceCodes\Application\EffectiveReferenceCode;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeException;
use PHPUnit\Framework\TestCase;

final class ReferenceCodeApiTest extends TestCase
{
    public function testDefinesExactlySixTenantOperations(): void
    {
        $operations = ReferenceCodeRuntimeFactory::operations();

        self::assertSame([
            'listReferenceCodeSets',
            'listReferenceCodes',
            'getReferenceCode',
            'createReferenceCode',
            'replaceReferenceCode',
            'retireReferenceCode',
        ], array_keys($operations));
        foreach ($operations as $operation) {
            self::assertSame('tenant', $operation->audience);
            self::assertSame('peanut.reference-codes', $operation->moduleKey);
            self::assertSame('none', $operation->dataAuthorization);
            self::assertSame('none', $operation->targetCardinality);
        }
    }

    public function testReadOperationsUseTheExactMethodsPathsAndPermission(): void
    {
        $operations = ReferenceCodeRuntimeFactory::operations();

        self::assertOperation(
            $operations['listReferenceCodeSets'],
            'GET',
            '/api/v1/reference-code-sets',
            'peanut.reference-codes.read',
            false,
        );
        self::assertOperation(
            $operations['listReferenceCodes'],
            'GET',
            '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
            'peanut.reference-codes.read',
            false,
        );
        self::assertOperation(
            $operations['getReferenceCode'],
            'GET',
            '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
            'peanut.reference-codes.read',
            false,
        );
    }

    public function testCommandOperationsUseTheExactMethodsPathsPermissionAndAtomicContract(): void
    {
        $operations = ReferenceCodeRuntimeFactory::operations();

        self::assertOperation(
            $operations['createReferenceCode'],
            'POST',
            '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
            'peanut.reference-codes.manage',
            true,
        );
        self::assertOperation(
            $operations['replaceReferenceCode'],
            'PUT',
            '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
            'peanut.reference-codes.manage',
            true,
        );
        self::assertOperation(
            $operations['retireReferenceCode'],
            'DELETE',
            '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
            'peanut.reference-codes.manage',
            true,
        );
    }

    public function testCreateInputAcceptsOnlyTheExactRequiredFields(): void
    {
        $input = ReferenceCodeRuntimeFactory::versionInput([
            'code' => 'sample-code',
            'label' => 'Sample label',
            'metadata' => ['flag' => true],
            'status' => 'active',
            'sort_order' => 4,
            'effective_at' => '2026-07-20T00:00:00.000Z',
            'expires_at' => null,
        ], true);

        self::assertSame('sample-code', $input['code']);
        self::assertSame('Sample label', $input['label']);
        self::assertSame(['flag' => true], $input['metadata']);
        self::assertSame('active', $input['status']);
        self::assertSame(4, $input['sortOrder']);
        self::assertSame('2026-07-20T00:00:00.000+00:00', $input['effectiveAt']->format('Y-m-d\TH:i:s.vP'));
        self::assertNull($input['expiresAt']);
    }

    public function testReplaceInputForbidsCodeAndRejectsMissingOrUnknownFields(): void
    {
        $valid = [
            'label' => 'Replacement',
            'metadata' => [],
            'status' => 'inactive',
            'sort_order' => 0,
            'effective_at' => '2026-07-20T00:00:00.000Z',
            'expires_at' => null,
        ];
        self::assertArrayNotHasKey('code', ReferenceCodeRuntimeFactory::versionInput($valid, false));

        foreach ([
            $valid + ['unknown' => true],
            array_diff_key($valid, ['label' => true]),
            ['code' => 'must-not-change'] + $valid,
        ] as $invalid) {
            $this->expectReferenceCodeError(
                'REFERENCE_CODE_REQUEST_INVALID',
                422,
                static fn() => ReferenceCodeRuntimeFactory::versionInput($invalid, false),
            );
        }
    }

    public function testListQueryDefaultsAndAcceptsOnlyFixedValues(): void
    {
        self::assertSame([
            'asOf' => null,
            'effectiveStatus' => 'all',
            'includeRetired' => false,
            'page' => 1,
            'pageSize' => 50,
        ], ReferenceCodeRuntimeFactory::listQuery([]));

        $query = ReferenceCodeRuntimeFactory::listQuery([
            'as_of' => '2026-07-20T08:09:10.123+08:00',
            'effective_status' => 'inactive',
            'include_retired' => 'true',
            'page' => '10000',
            'page_size' => '100',
        ]);
        self::assertSame('2026-07-20T00:09:10.123+00:00', $query['asOf']?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('inactive', $query['effectiveStatus']);
        self::assertTrue($query['includeRetired']);
        self::assertSame(10000, $query['page']);
        self::assertSame(100, $query['pageSize']);
    }

    public function testListQueryRejectsUnknownMalformedAndOutOfRangeValues(): void
    {
        foreach ([
            ['unknown' => 'value'],
            ['effective_status' => 'custom'],
            ['include_retired' => '1'],
            ['page' => '0'],
            ['page' => '10001'],
            ['page_size' => '101'],
            ['page_size' => '1.0'],
            ['as_of' => '2026-07-20T00:00:00.000001Z'],
        ] as $query) {
            $this->expectReferenceCodeError(
                str_contains((string) ($query['as_of'] ?? ''), '000001')
                    ? 'REFERENCE_CODE_INTERVAL_INVALID'
                    : 'REFERENCE_CODE_REQUEST_INVALID',
                422,
                static fn() => ReferenceCodeRuntimeFactory::listQuery($query),
            );
        }
    }

    public function testDetailQueryAcceptsOnlyOptionalExactMillisecondAsOf(): void
    {
        self::assertNull(ReferenceCodeRuntimeFactory::detailQuery([]));
        self::assertSame(
            '2026-07-20T00:00:00.000+00:00',
            ReferenceCodeRuntimeFactory::detailQuery(['as_of' => '2026-07-20T00:00:00Z'])?->format('Y-m-d\TH:i:s.vP'),
        );

        foreach ([['other' => 'x'], ['as_of' => 'invalid'], ['as_of' => ['invalid']]] as $query) {
            $this->expectReferenceCodeError(
                isset($query['other']) ? 'REFERENCE_CODE_REQUEST_INVALID' : 'REFERENCE_CODE_INTERVAL_INVALID',
                422,
                static fn() => ReferenceCodeRuntimeFactory::detailQuery($query),
            );
        }
    }

    public function testDeleteRequiresAnEmptyBody(): void
    {
        ReferenceCodeRuntimeFactory::assertEmptyBody([]);
        self::addToAssertionCount(1);

        $this->expectReferenceCodeError(
            'REFERENCE_CODE_REQUEST_INVALID',
            422,
            static fn() => ReferenceCodeRuntimeFactory::assertEmptyBody(['reason' => 'not-supported']),
        );
    }

    public function testEntryShapeAndLocationContainNoInternalIdentifiers(): void
    {
        $entry = self::entry();
        $item = ReferenceCodeRuntimeFactory::item($entry);

        self::assertSame([
            'module_key',
            'set_key',
            'code',
            'lifecycle',
            'revision',
            'etag',
            'effective',
            'created_at',
            'updated_at',
            'retired_at',
        ], array_keys($item));
        self::assertSame(
            '/api/v1/reference-code-sets/example.owner/generic-codes/codes/sample-code',
            ReferenceCodeRuntimeFactory::location($entry),
        );
        self::assertStringNotContainsString('tenant', json_encode($item, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('member', json_encode($item, JSON_THROW_ON_ERROR));
    }

    public function testHttpResponseSetsNoStoreRequestIdEtagLocationAndProblemContentType(): void
    {
        $success = ReferenceCodeRuntimeFactory::httpResponse(new ExternalOperationResponse(201, [
            'data' => self::entry()->toArray(),
        ]), 'req_reference_http_0001');
        self::assertSame('application/json', $success->getHeader('Content-Type'));
        self::assertSame('no-store', $success->getHeader('Cache-Control'));
        self::assertSame('req_reference_http_0001', $success->getHeader('X-Request-Id'));
        self::assertSame('"rev-1"', $success->getHeader('ETag'));
        self::assertSame(['data', 'meta'], array_keys($success->getData()));
        self::assertSame('req_reference_http_0001', $success->getData()['meta']['request_id']);
        self::assertSame(
            '/api/v1/reference-code-sets/example.owner/generic-codes/codes/sample-code',
            $success->getHeader('Location'),
        );

        $problem = ReferenceCodeRuntimeFactory::httpResponse(new ExternalOperationResponse(
            404,
            ['code' => 'REFERENCE_CODE_NOT_FOUND'],
            'application/problem+json',
        ), 'req_reference_http_0002');
        self::assertSame('application/problem+json', $problem->getHeader('Content-Type'));
        self::assertSame('no-store', $problem->getHeader('Cache-Control'));
        self::assertSame(['code'], array_keys($problem->getData()));
    }

    public function testControllerIsAThinSixMethodOpenApiAdapter(): void
    {
        $reflection = new \ReflectionClass(ReferenceCodeController::class);
        $methods = array_values(array_filter(
            array_map(static fn(\ReflectionMethod $method): string => $method->name, $reflection->getMethods()),
            static fn(string $name): bool => $name !== '__construct',
        ));
        sort($methods, SORT_STRING);
        self::assertSame([
            'createReferenceCode',
            'getReferenceCode',
            'listReferenceCodeSets',
            'listReferenceCodes',
            'replaceReferenceCode',
            'retireReferenceCode',
        ], $methods);
    }

    private static function assertOperation(
        ExternalOperationDefinition $operation,
        string $method,
        string $path,
        string $permission,
        bool $command,
    ): void {
        self::assertSame($method, $operation->method);
        self::assertSame($path, $operation->path);
        self::assertSame([$permission], $operation->permission->permissionKeys);
        self::assertSame($command, $operation->atomicCommand);
        self::assertSame($command, $operation->idempotencyRequired);
    }

    private static function entry(): EffectiveReferenceCode
    {
        return new EffectiveReferenceCode(
            'example.owner',
            'generic-codes',
            'sample-code',
            'active',
            1,
            '"rev-1"',
            [
                'revision' => 1,
                'label' => 'Sample label',
                'metadata' => [],
                'status' => 'active',
                'sort_order' => 0,
                'effective_at' => '2026-07-20T00:00:00.000Z',
                'expires_at' => null,
            ],
            '2026-07-20T00:00:00.000Z',
            '2026-07-20T00:00:00.000Z',
            null,
            '2026-07-20T00:00:00.000Z',
            [
                'revision' => 1,
                'label' => 'Sample label',
                'metadata' => [],
                'status' => 'active',
                'sort_order' => 0,
                'effective_at' => '2026-07-20T00:00:00.000Z',
                'expires_at' => null,
            ],
        );
    }

    private function expectReferenceCodeError(string $code, int $status, callable $operation): void
    {
        try {
            $operation();
        } catch (ReferenceCodeException $exception) {
            self::assertSame($code, $exception->errorCode);
            self::assertSame($status, $exception->httpStatus);

            return;
        }
        self::fail("Expected reference-code error {$code}.");
    }
}
