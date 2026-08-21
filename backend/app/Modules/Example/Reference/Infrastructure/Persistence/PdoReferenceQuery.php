<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Infrastructure\Persistence;

use PDO;
use PeanutAdmin\App\Modules\Example\Reference\Contracts\ReferenceOption;
use PeanutAdmin\App\Modules\Example\Reference\Contracts\ReferenceQuery;
use PeanutAdmin\DataPermission\Constraint\PdoQueryConstraintCompiler;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoReferenceQuery implements ReferenceQuery
{
    public function __construct(
        private PDO $pdo,
        private DataPermissionEngine $authorization,
    ) {}

    public function candidates(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        string $capability,
        string $search = '',
    ): array {
        $operation = match ($capability) {
            'view' => 'list',
            'use' => 'use',
            'maintain' => 'maintain',
            default => throw new ModuleException('AUTHZ_OPERATION_UNDECLARED', 'Reference capability is invalid.'),
        };
        $search = trim($search);
        if (mb_strlen($search) > 100) {
            throw new ModuleException('REFERENCE_SEARCH_INVALID', 'Reference search is limited to 100 characters.');
        }
        $constraint = (new PdoQueryConstraintCompiler())->compile(
            $this->authorization->queryConstraint(
                $context,
                'example.reference-item',
                $operation,
                $targets,
            ),
        );
        $searchSql = $search === ''
            ? ''
            : " AND LOCATE(:search, CONCAT(item.code, ' ', item.name)) > 0";
        $parameters = $constraint->parameters;
        if ($search !== '') {
            $parameters['search'] = $search;
        }
        $statement = $this->pdo->prepare(<<<SQL
SELECT item.id, item.code, item.name, item.owner_type, item.owner_tenant_id
FROM pa_example_reference_item item
WHERE item.status = 'active' AND {$constraint->sql}{$searchSql}
ORDER BY item.code, item.id
LIMIT 100
SQL);
        $statement->execute($parameters);

        return array_values(array_map(
            static fn(array $row): ReferenceOption => new ReferenceOption(
                (string) $row['id'],
                (string) $row['code'],
                (string) $row['name'],
                (string) $row['owner_type'],
                $row['owner_tenant_id'] === null ? null : (int) $row['owner_tenant_id'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        ));
    }
}
