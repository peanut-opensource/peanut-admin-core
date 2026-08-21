<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Application;

use PDO;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemPolicyPublication;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Audit\AuditRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class WorkItemPolicyPublisher implements WorkItemPolicyPublication
{
    public function __construct(
        private PDO $pdo,
        private DataPermissionEngine $authorization,
        private AuditRepository $audit,
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
        $this->pdo->beginTransaction();
        try {
            $policy = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_work_item_view_policy (
    tenant_id, name, config_json, status, revision, created_by_member_id, created_at, updated_at
) VALUES (:tenant_id, :name, :config_json, 'active', 1, :member_id, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
            $policy->execute([
                'tenant_id' => $context->tenantId,
                'name' => $name,
                'config_json' => $configJson,
                'member_id' => $context->memberId,
            ]);
            $policyId = (string) $this->pdo->lastInsertId();
            $publication = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_work_item_policy_publication (
    tenant_id, policy_id, project_id, status, error_code, policy_revision, published_at, updated_at
) VALUES (:tenant_id, :policy_id, :project_id, 'published', NULL, 1, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
            foreach ($projects as $projectId) {
                $publication->execute([
                    'tenant_id' => $context->tenantId,
                    'policy_id' => $policyId,
                    'project_id' => $projectId,
                ]);
            }
            $this->audit($context, $policyId, $projects);
            $this->pdo->commit();

            return $policyId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<string> $projectIds */
    private function audit(TenantContext $context, string $policyId, array $projectIds): void
    {
        sort($projectIds, SORT_STRING);
        $digest = hash('sha256', implode("\n", array_map(
            static fn(string $id): string => 'example.project:' . $id,
            $projectIds,
        )));
        $this->audit->appendTenantMember(
            $context,
            'example.work-item.policy-published',
            'example.work-item.policy-publish',
            'example.work-item-view-policy',
            $policyId,
            null,
            null,
            count($projectIds),
            $digest,
        );
    }
}
