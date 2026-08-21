<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization;

use PDO;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetIdSet;
use PeanutAdmin\DataPermission\Target\ResolvedResourceTargets;
use PeanutAdmin\DataPermission\Target\ResourceTargetResolver;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoTargetResolver implements ResourceTargetResolver
{
    public function __construct(private PDO $pdo) {}

    public function resolveAndValidate(TenantContext $context, TypedResourceTargetSet $targets): ResolvedResourceTargets
    {
        if ($targets->targetIds === []) {
            return new ResolvedResourceTargets(new TypedResourceTargetCollection([$targets]));
        }
        $table = match ($targets->targetResourceKey) {
            'example.project' => 'pa_example_project',
            'example.queue' => 'pa_example_queue',
            default => throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown example target type.'),
        };
        $targetIds = TargetIdSet::fromStrings($targets->targetIds);
        $statement = $this->pdo->prepare(
            <<<SQL
SELECT COUNT(*)
FROM JSON_TABLE(
    ?,
    '$[*]' COLUMNS (target_id BIGINT UNSIGNED PATH '$')
) requested
LEFT JOIN {$table} target
  ON target.tenant_id = ?
 AND target.id = requested.target_id
 AND target.status = 'active'
WHERE target.id IS NULL
SQL,
        );
        $statement->execute([$targetIds->json(), $context->tenantId]);
        if ((int) $statement->fetchColumn() !== 0) {
            throw new ModuleException('AUTHZ_TARGET_NOT_FOUND', 'Target does not exist in the trusted tenant context.');
        }

        return new ResolvedResourceTargets(new TypedResourceTargetCollection([$targets]));
    }
}
