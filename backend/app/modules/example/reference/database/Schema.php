<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Database;

final class Schema
{
    public static function createItem(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_reference_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` VARCHAR(16) NOT NULL,
  `owner_tenant_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_example_reference_code` (`owner_type`, `owner_tenant_id`, `code`),
  KEY `idx_example_reference_owner` (`owner_type`, `owner_tenant_id`, `status`, `id`),
  CONSTRAINT `fk_example_reference_owner_tenant` FOREIGN KEY (`owner_tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_reference_owner` CHECK ((`owner_type` = 'deployment' AND `owner_tenant_id` IS NULL) OR (`owner_type` = 'tenant' AND `owner_tenant_id` IS NOT NULL)),
  CONSTRAINT `chk_example_reference_status` CHECK (`status` IN ('active','disabled','archived'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }

    public static function createScope(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_reference_scope` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_item_id` BIGINT UNSIGNED NOT NULL,
  `scope_kind` VARCHAR(24) NOT NULL,
  `target_tenant_id` BIGINT UNSIGNED NULL,
  `target_resource_key` VARCHAR(160) NULL,
  `target_id` VARCHAR(128) NULL,
  `capability` VARCHAR(16) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_example_reference_scope_candidate` (`target_tenant_id`, `target_resource_key`, `target_id`, `capability`, `status`, `reference_item_id`),
  KEY `idx_example_reference_scope_item` (`reference_item_id`, `capability`, `status`),
  CONSTRAINT `fk_example_reference_scope_item` FOREIGN KEY (`reference_item_id`) REFERENCES `pa_example_reference_item` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_example_reference_scope_tenant` FOREIGN KEY (`target_tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_reference_scope_kind` CHECK (`scope_kind` IN ('all_tenants','tenant','typed_target')),
  CONSTRAINT `chk_example_reference_scope_capability` CHECK (`capability` IN ('view','use','maintain')),
  CONSTRAINT `chk_example_reference_scope_status` CHECK (`status` IN ('active','disabled')),
  CONSTRAINT `chk_example_reference_scope_shape` CHECK (
    (`scope_kind` = 'all_tenants' AND `target_tenant_id` IS NULL AND `target_resource_key` IS NULL AND `target_id` IS NULL)
    OR (`scope_kind` = 'tenant' AND `target_tenant_id` IS NOT NULL AND `target_resource_key` IS NULL AND `target_id` IS NULL)
    OR (`scope_kind` = 'typed_target' AND `target_tenant_id` IS NOT NULL AND `target_resource_key` IS NOT NULL AND `target_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }
}
