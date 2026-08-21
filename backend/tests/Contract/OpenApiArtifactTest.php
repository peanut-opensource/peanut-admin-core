<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class OpenApiArtifactTest extends TestCase
{
    public function testGeneratedRouteAndTypeArtifactsAreCompleteAndUnique(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = require $root . '/backend/route/openapi-generated.php';
        $operationIds = array_map(static fn(array $binding): string => $binding[3], $routes);

        self::assertCount(139, $routes);
        self::assertCount(139, array_unique($operationIds));
        self::assertArrayHasKey('GET /api/v1/account', $routes);
        self::assertArrayHasKey('PATCH /api/v1/account', $routes);
        self::assertArrayHasKey('POST /api/v1/account/password', $routes);
        self::assertArrayHasKey('GET /api/v1/authorization/target-candidates', $routes);
        self::assertArrayHasKey('GET /api/v1/members/{member_id}/effective-access', $routes);
        self::assertArrayHasKey('GET /api/v1/example/reference-items/candidates', $routes);
        self::assertArrayHasKey('PUT /api/platform/v1/tenants/{tenant_id}/modules/{module_key}', $routes);
        self::assertArrayHasKey('GET /api/platform/v1/settings', $routes);
        self::assertArrayHasKey('PUT /api/platform/v1/settings/{module_key}/{setting_key}', $routes);
        self::assertArrayHasKey('DELETE /api/platform/v1/settings/{module_key}/{setting_key}', $routes);
        self::assertArrayHasKey('GET /api/v1/settings', $routes);
        self::assertArrayHasKey('PUT /api/v1/settings/{module_key}/{setting_key}', $routes);
        self::assertArrayHasKey('DELETE /api/v1/settings/{module_key}/{setting_key}', $routes);
        self::assertArrayHasKey('GET /api/v1/reference-code-sets', $routes);
        self::assertArrayHasKey('GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes', $routes);
        self::assertArrayHasKey('GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}', $routes);
        self::assertArrayHasKey('POST /api/v1/reference-code-sets/{module_key}/{set_key}/codes', $routes);
        self::assertArrayHasKey('PUT /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}', $routes);
        self::assertArrayHasKey('DELETE /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}', $routes);
        self::assertArrayHasKey('GET /api/v1/files', $routes);
        self::assertArrayHasKey('POST /api/v1/files', $routes);
        self::assertArrayHasKey('GET /api/v1/files/{file_key}', $routes);
        self::assertArrayHasKey('GET /api/v1/files/{file_key}/content', $routes);
        self::assertArrayHasKey('DELETE /api/v1/files/{file_key}', $routes);
        self::assertArrayHasKey('GET /api/v1/file-assets', $routes);
        self::assertArrayHasKey('POST /api/v1/files/{file_key}/delivery-grants', $routes);
        self::assertArrayHasKey('GET /api/v1/file-deliveries/{file_key}', $routes);
        self::assertArrayHasKey('GET /api/v1/tasks', $routes);
        self::assertArrayHasKey('POST /api/v1/tasks/{job_key}/cancel', $routes);
        self::assertArrayHasKey('GET /api/v1/notifications', $routes);
        self::assertArrayHasKey('POST /api/v1/notifications', $routes);
        self::assertArrayHasKey('GET /api/v1/audit-events/{event_id}', $routes);
        self::assertArrayHasKey('GET /api/v1/menu-diagnostics', $routes);
        self::assertArrayHasKey('GET /api/platform/v1/audit-events/{event_id}', $routes);
        self::assertArrayHasKey('GET /api/platform/v1/menu-diagnostics', $routes);
        self::assertArrayHasKey('GET /api/platform/v1/upgrade', $routes);
        self::assertArrayHasKey('GET /api/v1/import-export/operations', $routes);
        self::assertArrayHasKey('POST /api/v1/import-export/imports', $routes);
        self::assertArrayHasKey('GET /api/v1/integration-security/machine-identities', $routes);
        self::assertArrayHasKey('POST /api/v1/integration-security/sessions/{session_key}/revoke', $routes);
        self::assertArrayHasKey('GET /api/platform/v1/ops/status', $routes);
        self::assertArrayHasKey('POST /api/platform/v1/ops/tasks/backup', $routes);
        self::assertArrayHasKey('PUT /api/platform/v1/ops/maintenance', $routes);

        $types = (string) file_get_contents($root . '/packages/web/admin-core/src/generated/api.d.ts');
        self::assertStringContainsString('listExampleWorkItems', $types);
        self::assertStringContainsString('TargetSet', $types);
        self::assertStringContainsString('SelectTenantRequest', $types);
        self::assertStringContainsString('MemberEffectiveAccessResponse', $types);
        self::assertStringContainsString('SettingListResponse', $types);
        self::assertStringContainsString('ReplaceSettingRequest', $types);
        self::assertStringContainsString('ReferenceCodeListResponse', $types);
        self::assertStringContainsString('ReferenceCodeReplaceRequest', $types);
        self::assertStringContainsString('FileListResponse', $types);
        self::assertStringContainsString('TaskListResponse', $types);
        self::assertStringContainsString('NotificationListResponse', $types);
        self::assertStringContainsString('UpgradeStatusResponse', $types);
        self::assertStringContainsString('MenuDiagnosticListResponse', $types);
        self::assertStringContainsString('OperationListResponse', $types);
        self::assertStringContainsString('OperationResponse', $types);
        self::assertStringContainsString('ListResponse', $types);
        self::assertStringContainsString('ObjectResponse', $types);
        self::assertStringContainsString('PageResponse', $types);
        self::assertStringContainsString('ObjectResponse-2', $types);
        self::assertStringNotContainsString('tenant_id?: number', $types);
        self::assertStringNotContainsString('data: unknown', $types);
        self::assertDoesNotMatchRegularExpression('/(?:\| unknown|unknown \|)/', $types);
    }

    public function testAdministrationCommandsUseTheR02AtomicHostWithoutGenericIdempotencyMiddleware(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';

        foreach ([
            'PUT /api/platform/v1/settings/{module_key}/{setting_key}',
            'DELETE /api/platform/v1/settings/{module_key}/{setting_key}',
            'PUT /api/v1/settings/{module_key}/{setting_key}',
            'DELETE /api/v1/settings/{module_key}/{setting_key}',
            'POST /api/v1/reference-code-sets/{module_key}/{set_key}/codes',
            'PUT /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
            'DELETE /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
        ] as $route) {
            self::assertArrayHasKey($route, $routes);
            self::assertFalse($routes[$route][6], $route);
        }
    }

    public function testEffectiveAccessPreviewKeepsItsSensitiveReadContract(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';
        $route = $routes['GET /api/v1/members/{member_id}/effective-access'] ?? null;

        self::assertIsArray($route);
        self::assertSame(
            'PeanutAdmin\\App\\controller\\api\\v1\\DataAuthorizationController',
            $route[0],
        );
        self::assertSame('effectiveAccess', $route[1]);
        self::assertSame('core.member.effective-access.read', $route[2]);
        self::assertSame('getMemberEffectiveAccess', $route[3]);
        self::assertSame('tenant', $route[4]);
        self::assertTrue($route[5]);
        self::assertFalse($route[6]);
        self::assertNull($route[7]);
        self::assertSame(200, $route[8]);
        self::assertSame('application/json', $route[9]);
        self::assertSame(['Cache-Control', 'X-Request-Id'], $route[10]);
        self::assertSame('#/components/schemas/MemberEffectiveAccessResponse', $route[11]);
    }

    public function testGeneratedRoutesCarryTypedSuccessContracts(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';

        foreach ($routes as $route => $binding) {
            self::assertCount(12, $binding, $route);
            $status = $binding[8];
            $mediaType = $binding[9];
            $headers = $binding[10];
            $schema = $binding[11];

            self::assertContains($status, [200, 201, 202, 204], $route);
            self::assertContains($mediaType, $status === 204 ? [null] : ['application/json', '*/*'], $route);
            self::assertContains('X-Request-Id', $headers, $route);
            self::assertContains('Cache-Control', $headers, $route);
            self::assertSame($status === 204 ? null : true, $schema === null ? null : true, $route);
        }
    }

    public function testGeneratedRoutesKeepAudienceAndPermissionContractsSeparate(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';

        foreach ($routes as $route => $binding) {
            [$class, $method, $permission, $operationId, $audience, $requiresAuth, $idempotent, $moduleKey] = $binding;
            self::assertMatchesRegularExpression('/^[a-z][A-Za-z0-9]+$/', $operationId, $route);
            self::assertNotSame('', $class, $route);
            self::assertNotSame('', $method, $route);
            self::assertContains($audience, ['tenant', 'platform'], $route);
            self::assertIsBool($requiresAuth, $route);
            self::assertIsBool($idempotent, $route);
            self::assertTrue($permission === null || $requiresAuth, $route);
            self::assertTrue($moduleKey === null || ($audience === 'tenant' && $requiresAuth && $permission !== null), $route);

            if (str_starts_with($route, 'GET /api/platform/') || str_starts_with($route, 'POST /api/platform/')
                || str_starts_with($route, 'PUT /api/platform/') || str_starts_with($route, 'PATCH /api/platform/')
                || str_starts_with($route, 'DELETE /api/platform/')) {
                self::assertTrue($permission === null || str_starts_with($permission, 'platform.'), $route);

                continue;
            }

            self::assertFalse(is_string($permission) && str_starts_with($permission, 'platform.'), $route);
        }
    }

    public function testOptionalModuleRoutesDeclareTheirRuntimeModule(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';
        $expected = [
            'DELETE /api/v1/integration-security/machine-identities/{identity_key}' => 'peanut.integration-security',
            'DELETE /api/v1/integration-security/webhooks/{endpoint_key}' => 'peanut.integration-security',
            'DELETE /api/v1/files/{file_key}' => 'peanut.file-media',
            'DELETE /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}' => 'peanut.reference-codes',
            'DELETE /api/v1/settings/{module_key}/{setting_key}' => 'peanut.settings',
            'GET /api/v1/example/reference-items/candidates' => 'example.reference',
            'GET /api/v1/example/work-items' => 'example.work-item',
            'GET /api/v1/example/work-items/aggregate' => 'example.work-item',
            'GET /api/v1/example/work-items/{work_item_id}' => 'example.work-item',
            'GET /api/v1/file-assets' => 'peanut.file-media',
            'GET /api/v1/files' => 'peanut.file-media',
            'GET /api/v1/files/{file_key}' => 'peanut.file-media',
            'GET /api/v1/files/{file_key}/content' => 'peanut.file-media',
            'GET /api/v1/import-export/operations' => 'peanut.import-export',
            'GET /api/v1/import-export/operations/{operation_key}' => 'peanut.import-export',
            'GET /api/v1/integration-security/deliveries' => 'peanut.integration-security',
            'GET /api/v1/integration-security/deliveries/{delivery_key}/attempts' => 'peanut.integration-security',
            'GET /api/v1/integration-security/machine-identities' => 'peanut.integration-security',
            'GET /api/v1/integration-security/sessions' => 'peanut.integration-security',
            'GET /api/v1/integration-security/webhooks' => 'peanut.integration-security',
            'GET /api/v1/notifications' => 'peanut.notification-sms',
            'GET /api/v1/reference-code-sets' => 'peanut.reference-codes',
            'GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes' => 'peanut.reference-codes',
            'GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}' => 'peanut.reference-codes',
            'GET /api/v1/settings' => 'peanut.settings',
            'GET /api/v1/tasks' => 'peanut.task-job',
            'GET /api/v1/tasks/{job_key}' => 'peanut.task-job',
            'PATCH /api/v1/example/work-items/{work_item_id}' => 'example.work-item',
            'POST /api/v1/example/work-item-view-policies' => 'example.work-item',
            'POST /api/v1/example/work-items' => 'example.work-item',
            'POST /api/v1/files' => 'peanut.file-media',
            'POST /api/v1/files/{file_key}/delivery-grants' => 'peanut.file-media',
            'POST /api/v1/import-export/exports' => 'peanut.import-export',
            'POST /api/v1/import-export/imports' => 'peanut.import-export',
            'POST /api/v1/import-export/operations/{operation_key}/cancel' => 'peanut.import-export',
            'POST /api/v1/integration-security/machine-identities' => 'peanut.integration-security',
            'POST /api/v1/integration-security/machine-identities/{identity_key}/rotate' => 'peanut.integration-security',
            'POST /api/v1/integration-security/sessions/{session_key}/revoke' => 'peanut.integration-security',
            'POST /api/v1/integration-security/webhooks' => 'peanut.integration-security',
            'POST /api/v1/integration-security/webhooks/{endpoint_key}/rotate-secret' => 'peanut.integration-security',
            'POST /api/v1/notification-outbox/{outbox_key}/dispatch' => 'peanut.notification-sms',
            'POST /api/v1/notifications' => 'peanut.notification-sms',
            'POST /api/v1/notifications/bulk' => 'peanut.notification-sms',
            'POST /api/v1/notifications/{message_key}/read' => 'peanut.notification-sms',
            'POST /api/v1/reference-code-sets/{module_key}/{set_key}/codes' => 'peanut.reference-codes',
            'POST /api/v1/tasks/{job_key}/cancel' => 'peanut.task-job',
            'POST /api/v1/tasks/{job_key}/retry' => 'peanut.task-job',
            'PUT /api/v1/notification-templates/{template_key}' => 'peanut.notification-sms',
            'PUT /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}' => 'peanut.reference-codes',
            'PUT /api/v1/settings/{module_key}/{setting_key}' => 'peanut.settings',
        ];
        $actual = [];
        foreach ($routes as $route => $binding) {
            if ($binding[7] !== null) {
                $actual[$route] = $binding[7];
            }
        }

        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
    }

    public function testSignedFileDeliveryRouteUsesItsTokenInsteadOfBearerMiddleware(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';
        $route = $routes['GET /api/v1/file-deliveries/{file_key}'] ?? null;

        self::assertIsArray($route);
        self::assertNull($route[2]);
        self::assertSame('tenant', $route[4]);
        self::assertFalse($route[5]);
        self::assertNull($route[7]);
    }

    public function testExampleOperationsUseConcreteHandlers(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';

        foreach ($routes as $route => $binding) {
            if (!str_contains($route, '/api/v1/example/')) {
                continue;
            }

            self::assertNotSame(
                'PeanutAdmin\\App\\controller\\api\\v1\\ContractController',
                $binding[0],
                $route,
            );
        }
    }

    public function testStaticRoutePrecedesParameterRouteAtTheSamePathLevel(): void
    {
        $routes = require dirname(__DIR__, 3) . '/backend/route/openapi-generated.php';
        $orderedRoutes = array_keys($routes);
        $aggregate = array_search('GET /api/v1/example/work-items/aggregate', $orderedRoutes, true);
        $detail = array_search('GET /api/v1/example/work-items/{work_item_id}', $orderedRoutes, true);

        self::assertIsInt($aggregate);
        self::assertIsInt($detail);
        self::assertLessThan($detail, $aggregate);
    }
}
