<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Infrastructure\Authorization;

use PDO;
use PeanutAdmin\App\Modules\Example\Reference\Contracts\ReferenceScope;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetIdSet;
use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Constraint\AlwaysFalse;
use PeanutAdmin\DataPermission\Constraint\ColumnIn;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Constraint\QueryConstraint;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Decision\AuthorizationDecision;
use PeanutAdmin\DataPermission\Provider\SharedMasterScopeProvider;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoReferenceScopeProvider implements SharedMasterScopeProvider, ReferenceScope
{
    public function __construct(private PDO $pdo) {}

    public function compileVisiblePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
    ): QueryConstraint {
        $capability = match ($operation->operation) {
            'list' => 'view',
            'use' => 'use',
            'maintain' => 'maintain',
            default => throw new ModuleException(
                'AUTHZ_OPERATION_UNDECLARED',
                'Reference capability is not declared for this operation.',
            ),
        };
        $ids = $this->allowedIds($context, $targets, $capability);

        return $ids === [] ? new AlwaysFalse() : new ColumnIn(new ColumnReference('id'), $ids);
    }

    public function assertUsageAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        string $resourceId,
        TypedResourceTargetCollection $targets,
    ): AuthorizationDecision {
        return $this->canUse($context, $resourceId, $targets)
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::deny('AUTHZ_SHARED_MASTER_SCOPE_DENIED');
    }

    public function canUse(
        AuthorizationContext $context,
        string $referenceItemId,
        TypedResourceTargetCollection $targets,
    ): bool {
        return in_array($referenceItemId, $this->allowedIds($context, $targets, 'use'), true);
    }

    /** @return list<string> */
    public function allowedIds(
        AuthorizationContext $context,
        TypedResourceTargetCollection $targets,
        string $capability,
    ): array {
        if (!in_array($capability, ['view', 'use', 'maintain'], true)) {
            throw new ModuleException('AUTHZ_OPERATION_UNDECLARED', 'Reference capability is invalid.');
        }
        $typedTargets = [];
        foreach ($targets->sets as $targetSet) {
            if (!in_array($targetSet->targetResourceKey, ['example.project', 'example.queue'], true)) {
                throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown reference target type.');
            }
            foreach (TargetIdSet::fromStrings($targetSet->targetIds)->ids as $targetId) {
                $typedTargets[] = [
                    'resource_key' => $targetSet->targetResourceKey,
                    'target_id' => $targetId,
                ];
            }
        }
        $typedTargetJson = json_encode($typedTargets, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $clauses = [
            "scope.scope_kind = 'all_tenants'",
            "(scope.scope_kind = 'tenant' AND scope.target_tenant_id = ?)",
            <<<'SQL'
(scope.scope_kind = 'typed_target' AND scope.target_tenant_id = ? AND EXISTS (
    SELECT 1
    FROM JSON_TABLE(
        ?,
        '$[*]' COLUMNS (
            resource_key VARCHAR(160) PATH '$.resource_key',
            target_id VARCHAR(128) PATH '$.target_id'
        )
    ) requested
    WHERE requested.resource_key COLLATE utf8mb4_0900_ai_ci = scope.target_resource_key
      AND requested.target_id COLLATE utf8mb4_0900_ai_ci = scope.target_id
))
SQL,
        ];
        $parameters = [
            $capability,
            $context->tenant->tenantId,
            $context->tenant->tenantId,
            $typedTargetJson,
        ];
        $statement = $this->pdo->prepare(sprintf(<<<'SQL'
SELECT DISTINCT CAST(item.id AS CHAR) AS id
FROM pa_example_reference_item item
INNER JOIN pa_example_reference_scope scope ON scope.reference_item_id = item.id
WHERE item.status = 'active' AND scope.status = 'active' AND scope.capability = ?
  AND (%s)
ORDER BY id
SQL, implode(' OR ', $clauses)));
        $statement->execute($parameters);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
