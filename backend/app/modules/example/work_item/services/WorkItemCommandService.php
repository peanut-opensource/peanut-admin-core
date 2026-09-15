<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\services;

use PeanutAdmin\App\modules\example\work_item\contracts\CreateWorkItem;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemCommands;
use PeanutAdmin\App\modules\example\work_item\model\WorkItem;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Membership\services\MemberAdminService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\facade\Db;

final readonly class WorkItemCommandService implements WorkItemCommands
{
    public function __construct(
        private DataPermissionEngine $authorization,
        private AuditService $audit,
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
        return Db::transaction(function () use ($context, $command, $departmentId, $title): string {
            $record = new WorkItem();
            $record->save([
                'tenant_id' => $context->tenantId,
                'project_id' => $command->projectId,
                'queue_id' => $command->queueId,
                'reference_item_id' => $command->referenceItemId,
                'owner_member_id' => $context->memberId,
                'department_id' => $departmentId,
                'title' => $title,
                'status' => 'open',
                'revision' => 1,
                'created_by_member_id' => $context->memberId,
                'created_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
            ]);
            $workItemId = (string) $record->getAttr('id');
            $this->audit->tenantMember(
                $context,
                'example.work-item.created',
                'example.work-item.create',
                'example.work-item',
                $workItemId,
                targetCount: 1,
                boundaryTargetType: 'example.project',
                boundaryTargetId: $command->projectId,
            );
            return $workItemId;
        });
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

        return Db::transaction(function () use ($context, $workItemId, $expectedRevision, $targets, $title, $status): array {
            $record = WorkItem::scope('tenant', $this->scope($context))
                ->where('id', $workItemId)
                ->lock(true)
                ->find();
            if (!$record instanceof WorkItem) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ((int) $record->getAttr('revision') !== $expectedRevision) {
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
                || !$this->contains($targets, 'example.project', (string) $record->getAttr('project_id'), 'primary')) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ($record->getAttr('queue_id') !== null
                && ($targets->countForRole('related') !== 1
                    || !$this->contains($targets, 'example.queue', (string) $record->getAttr('queue_id'), 'related'))) {
                throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
            }
            if ($record->getAttr('queue_id') === null && $targets->countForRole('related') !== 0) {
                throw new ModuleException('AUTHZ_TARGET_CARDINALITY_INVALID', 'Update does not accept an unused related target.');
            }
            $changes = [
                'revision' => Db::raw('revision + 1'),
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
            ];
            if ($title !== null) {
                $changes['title'] = trim($title);
            }
            if ($status !== null) {
                $changes['status'] = $status;
            }
            $updated = WorkItem::scope('tenant', $this->scope($context))
                ->where('id', $workItemId)
                ->where('revision', $expectedRevision)
                ->update($changes);
            if ($updated !== 1) {
                throw new ModuleException('REVISION_MISMATCH', 'The work item revision has changed.');
            }
            $this->audit->tenantMember(
                $context,
                'example.work-item.updated',
                'example.work-item.update',
                'example.work-item',
                $workItemId,
                targetCount: 1,
                boundaryTargetType: 'example.project',
                boundaryTargetId: (string) $record->getAttr('project_id'),
            );
            return ['id' => $workItemId, 'revision' => $expectedRevision + 1];
        });
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

    private function scope(TenantContext $context): TenantScope
    {
        return TenantScope::fromTrustedContext($context->tenantId, $context->requestId);
    }

}
