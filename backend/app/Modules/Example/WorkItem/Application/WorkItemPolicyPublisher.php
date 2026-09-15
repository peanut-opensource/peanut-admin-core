<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Application;

use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemPolicyPublication;
use PeanutAdmin\App\Modules\Example\WorkItem\Model\WorkItemPolicyPublication as WorkItemPolicyPublicationRecord;
use PeanutAdmin\App\Modules\Example\WorkItem\Model\WorkItemViewPolicy;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Db;

final readonly class WorkItemPolicyPublisher implements WorkItemPolicyPublication
{
    public function __construct(
        private DataPermissionEngine $authorization,
        private AuditService $audit,
    ) {}

    /** @param array<string, mixed> $config */
    public function publish(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        string $name,
        array $config,
    ): string {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new ModuleException('WORK_ITEM_POLICY_NAME_INVALID', 'Policy name is required and limited to 160 characters.');
        }
        $configJson = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($configJson) > 65_535) {
            throw new ModuleException('WORK_ITEM_POLICY_CONFIG_INVALID', 'Policy config is limited to 65535 bytes.');
        }
        $decision = $this->authorization->decideTargets(
            $context,
            'example.work-item',
            'policy-publish',
            $targets,
        );
        if (!$decision->allowed) {
            throw new ModuleException($decision->reasonCode, 'Policy targets are outside the effective data policy.');
        }
        if (count($targets->sets) !== 1
            || $targets->sets[0]->targetResourceKey !== 'example.project'
            || $targets->sets[0]->targetRole !== 'primary') {
            throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Policy publication accepts one Project target set.');
        }
        $projects = $targets->sets[0]->targetIds;
        if ($projects === [] || count($projects) > 500) {
            throw new ModuleException('AUTHZ_TARGET_CARDINALITY_INVALID', 'Policy publication requires 1 to 500 Projects.');
        }
        return Db::transaction(function () use ($context, $name, $config, $projects): string {
            $policy = new WorkItemViewPolicy();
            $policy->save([
                'tenant_id' => $context->tenantId,
                'name' => $name,
                'config_json' => $config,
                'status' => 'active',
                'revision' => 1,
                'created_by_member_id' => $context->memberId,
                'created_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
            ]);
            $policyId = (string) $policy->getAttr('id');
            foreach ($projects as $projectId) {
                (new WorkItemPolicyPublicationRecord())->save([
                    'tenant_id' => $context->tenantId,
                    'policy_id' => $policyId,
                    'project_id' => $projectId,
                    'status' => 'published',
                    'error_code' => null,
                    'policy_revision' => 1,
                    'published_at' => Db::raw('UTC_TIMESTAMP(3)'),
                    'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
                ]);
            }
            $this->audit($context, $policyId, $projects);
            return $policyId;
        });
    }

    /** @param list<string> $projectIds */
    private function audit(TenantContext $context, string $policyId, array $projectIds): void
    {
        sort($projectIds, SORT_STRING);
        $digest = hash('sha256', implode("\n", array_map(
            static fn(string $id): string => 'example.project:' . $id,
            $projectIds,
        )));
        $this->audit->tenantMember(
            $context,
            'example.work-item.policy-published',
            'example.work-item.policy-publish',
            'example.work-item-view-policy',
            $policyId,
            targetCount: count($projectIds),
            targetSetDigest: $digest,
        );
    }
}
