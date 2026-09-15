<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\infrastructure\persistence;

use PeanutAdmin\App\modules\example\reference\contracts\ReferenceOption;
use PeanutAdmin\App\modules\example\reference\contracts\ReferenceQuery;
use PeanutAdmin\App\modules\example\reference\model\ReferenceItem;
use PeanutAdmin\DataPermission\Constraint\ThinkPhpQueryConstraintApplier;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class ThinkPhpReferenceQuery implements ReferenceQuery
{
    public function __construct(private DataPermissionEngine $authorization) {}

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
        $query = ReferenceItem::where('status', 'active');
        (new ThinkPhpQueryConstraintApplier())->apply(
            $query,
            $this->authorization->queryConstraint(
                $context,
                'example.reference-item',
                $operation,
                $targets,
            ),
        );
        if ($search !== '') {
            $query->where(function ($nested) use ($search): void {
                $nested->whereLike('code', '%' . $search . '%')
                    ->whereOr('name', 'like', '%' . $search . '%');
            });
        }

        $items = [];
        foreach ($query->order('code')->order('id')->limit(100)->select()->toArray() as $record) {
            $items[] = new ReferenceOption(
                (string) $record['id'],
                (string) $record['code'],
                (string) $record['name'],
                (string) $record['owner_type'],
                $record['owner_tenant_id'] === null ? null : (int) $record['owner_tenant_id'],
            );
        }

        return $items;
    }
}
