<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Application;

use PDO;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\CreateWorkItem;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemCommands;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Audit\AuditRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class WorkItemCommandService implements WorkItemCommands
{
    public function __construct(
        private PDO $pdo,
        private DataPermissionEngine $authorization,
        private AuditRepository $audit,
        private MemberAdminService $members,
    ) {}

    public function create(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        CreateWorkItem $command,
    ): string {
        $title = trim($command->title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new ModuleException('WORK_ITEM_TITLE_INVALID', 'Work item title is required and limited to 200 characters.');
        }
        $decision = $this->authorization->decideCreate(
            $context,
            'example.work-item',
            'create',
            $targets,
        );
        if (!$decision->allowed) {
            throw new ModuleException($decision->reasonCode, 'Create targets are outside the effective data policy.');
        }
        if ($targets->countForRole('primary') !== 1
            || !$this->contains($targets, 'example.project', $command->projectId, 'primary')) {
            throw new ModuleException('AUTHZ_TARGET_CARDINALITY_INVALID', 'Create requires exactly the authorized Project.');
        }
        $relatedCount = $targets->countForRole('related');
        if ($command->queueId === null && $relatedCount !== 0) {
            throw new ModuleException('AUTHZ_TARGET_CARDINALITY_INVALID', 'Create does not accept an unused related target.');
        }
        if ($command->queueId !== null
            && ($relatedCount !== 1 || !$this->contains($targets, 'example.queue', $command->queueId, 'related'))) {
            throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Queue must be explicitly authorized as Queue.');
        }
        $departmentId = $this->memberDepartment($context);
        $referenceDecision = $this->authorization->decideTargets(
            $context,
            'example.reference-item',
            'use',
            new TypedResourceTargetCollection([
                new TypedResourceTargetSet('example.project', [$command->projectId]),
            ]),
            $command->referenceItemId,
        );
        if (!$referenceDecision->allowed) {
            throw new ModuleException('AUTHZ_SHARED_MASTER_SCOPE_DENIED', 'Reference item is outside the selected target scope.');
        }
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_work_item (
    tenant_id, project_id, queue_id, reference_item_id, owner_member_id,
    department_id, title, status, revision, created_by_member_id, created_at, updated_at
) VALUES (
    :tenant_id, :project_id, :queue_id, :reference_item_id, :owner_member_id,
    :department_id, :title, 'open', 1, :created_by_member_id, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
)
SQL);
            $statement->execute([
                'tenant_id' => $context->tenantId,
                'project_id' => $command->projectId,
                'queue_id' => $command->queueId,
                'reference_item_id' => $command->referenceItemId,
                'owner_member_id' => $context->memberId,
                'department_id' => $departmentId,
                'title' => $title,
                'created_by_member_id' => $context->memberId,
            ]);
            $workItemId = (string) $this->pdo->lastInsertId();
            $this->audit->appendTenantMember(
                $context,
                'example.work-item.created',
                'example.work-item.create',
                'example.work-item',
                $workItemId,
                'example.project',
                $command->projectId,
                1,
            );
            $this->pdo->commit();

            return $workItemId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array{id: string, revision: int} */
    public function update(
        TenantContext $context,
        string $workItemId,
        int $expectedRevision,
        TypedResourceTargetCollection $targets,
        ?string $title,
        ?string $status,
    ): array {
        if ($title === null && $status === null) {
            throw new ModuleException('WORK_ITEM_UPDATE_EMPTY', 'At least one editable field is required.');
        }
        if ($title !== null && (trim($title) === '' || mb_strlen(trim($title)) > 200)) {
            throw new ModuleException('WORK_ITEM_TITLE_INVALID', 'Work item title is required and limited to 200 characters.');
        }
        if ($status !== null && !in_array($status, ['open', 'active', 'closed'], true)) {
            throw new ModuleException('WORK_ITEM_STATUS_INVALID', 'The work item status is invalid.');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT project_id, queue_id, revision
FROM pa_example_work_item
WHERE tenant_id = :tenant_id AND id = :work_item_id
FOR UPDATE
SQL);
            $statement->execute(['tenant_id' => $context->tenantId, 'work_item_id' => $workItemId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ((int) $row['revision'] !== $expectedRevision) {
                throw new ModuleException('REVISION_MISMATCH', 'The work item revision has changed.');
            }
            $decision = $this->authorization->decideTargets(
                $context,
                'example.work-item',
                'update',
                $targets,
            );
            if (!$decision->allowed) {
                throw new ModuleException($decision->reasonCode, 'Update targets are outside the effective data policy.');
            }
            if ($targets->countForRole('primary') !== 1
                || !$this->contains($targets, 'example.project', (string) $row['project_id'], 'primary')) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ($row['queue_id'] !== null
                && ($targets->countForRole('related') !== 1
                    || !$this->contains($targets, 'example.queue', (string) $row['queue_id'], 'related'))) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ($row['queue_id'] === null && $targets->countForRole('related') !== 0) {
                throw new ModuleException('AUTHZ_TARGET_CARDINALITY_INVALID', 'Update does not accept an unused related target.');
            }
            $updated = $this->pdo->prepare(<<<'SQL'
UPDATE pa_example_work_item
SET title = COALESCE(:title, title), status = COALESCE(:status, status),
    revision = revision + 1, updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND id = :work_item_id AND revision = :expected_revision
SQL);
            $updated->execute([
                'title' => $title === null ? null : trim($title),
                'status' => $status,
                'tenant_id' => $context->tenantId,
                'work_item_id' => $workItemId,
                'expected_revision' => $expectedRevision,
            ]);
            if ($updated->rowCount() !== 1) {
                throw new ModuleException('REVISION_MISMATCH', 'The work item revision has changed.');
            }
            $this->audit->appendTenantMember(
                $context,
                'example.work-item.updated',
                'example.work-item.update',
                'example.work-item',
                $workItemId,
                'example.project',
                (string) $row['project_id'],
                1,
            );
            $this->pdo->commit();

            return ['id' => $workItemId, 'revision' => $expectedRevision + 1];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function bulkWrite(): never
    {
        throw new ModuleException('AUTHZ_BULK_WRITE_DISABLED', 'Ordinary bulk write is disabled in the P0 example.');
    }

    private function contains(
        TypedResourceTargetCollection $targets,
        string $resourceKey,
        string $id,
        string $role,
    ): bool {
        foreach ($targets->sets as $set) {
            if ($set->targetResourceKey === $resourceKey
                && $set->targetRole === $role
                && in_array($id, $set->targetIds, true)) {
                return true;
            }
        }
        return false;
    }

    private function memberDepartment(TenantContext $context): ?int
    {
        $member = $this->members->get($context->tenantId, $context->memberId);
        if ($member['status'] !== 'active') {
            throw new ModuleException('AUTHZ_DATA_DENIED', 'The active member context is unavailable.');
        }
        $departmentId = $member['primary_department_id'];

        return $departmentId === null ? null : (int) $departmentId;
    }

}
