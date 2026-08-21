<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Database;

final class Schema
{
    public static function createProject(): string
    {
        return <<<'SQL'
CREATE TABLE `pa_example_project` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_example_project_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_example_project_code` (`tenant_id`, `code`),
  CONSTRAINT `fk_example_project_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_example_project_status` CHECK (`status` IN ('active','disabled','archived'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
    }

    public static function createQueue(): string
    {
        return str_replace(
            ['pa_example_project', 'example_project', 'Project'],
            ['pa_example_queue', 'example_queue', 'Queue'],
            self::createProject(),
        );
    }
}
