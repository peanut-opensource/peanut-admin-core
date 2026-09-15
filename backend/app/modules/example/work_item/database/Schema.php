<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Database;

final class Schema
{
    /** @return list<string> */
    public static function createStatements(): array
    {
        return [self::workItem(), self::viewPolicy(), self::policyPublication()];
    }

    private static function workItem(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_work_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `queue_id` BIGINT UNSIGNED NULL,
  `reference_item_id` BIGINT UNSIGNED NOT NULL,
  `owner_member_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_example_work_item_tenant_id` (`tenant_id`, `id`),
  KEY `idx_example_work_item_project` (`tenant_id`, `project_id`, `status`, `id`),
  KEY `idx_example_work_item_queue` (`tenant_id`, `queue_id`, `status`, `id`),
  KEY `idx_example_work_item_reference` (`tenant_id`, `reference_item_id`, `id`),
  CONSTRAINT `fk_example_work_item_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_work_item_project` FOREIGN KEY (`tenant_id`, `project_id`) REFERENCES `pa_example_project` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_work_item_queue` FOREIGN KEY (`tenant_id`, `queue_id`) REFERENCES `pa_example_queue` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_work_item_owner` FOREIGN KEY (`tenant_id`, `owner_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_work_item_creator` FOREIGN KEY (`tenant_id`, `created_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_work_item_department` FOREIGN KEY (`tenant_id`, `department_id`) REFERENCES `pa_department` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_work_item_status` CHECK (`status` IN ('open','active','closed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }

    private static function viewPolicy(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_work_item_view_policy` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `config_json` JSON NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_example_view_policy_tenant_id` (`tenant_id`, `id`),
  KEY `idx_example_view_policy_status` (`tenant_id`, `status`, `id`),
  CONSTRAINT `fk_example_view_policy_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_view_policy_creator` FOREIGN KEY (`tenant_id`, `created_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_view_policy_status` CHECK (`status` IN ('draft','active','archived'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }

    private static function policyPublication(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_work_item_policy_publication` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `policy_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `error_code` VARCHAR(96) NULL,
  `policy_revision` BIGINT UNSIGNED NOT NULL,
  `published_at` DATETIME(3) NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_example_policy_publication` (`tenant_id`, `policy_id`, `project_id`),
  KEY `idx_example_policy_publication_project` (`tenant_id`, `project_id`, `status`, `id`),
  CONSTRAINT `fk_example_policy_publication_policy` FOREIGN KEY (`tenant_id`, `policy_id`) REFERENCES `pa_example_work_item_view_policy` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_policy_publication_project` FOREIGN KEY (`tenant_id`, `project_id`) REFERENCES `pa_example_project` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_policy_publication_status` CHECK (`status` IN ('pending','published','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }
}
