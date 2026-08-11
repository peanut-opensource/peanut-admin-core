<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Database;

use InvalidArgumentException;

final class Schema
{
    /** @var array<string, string> */
    private const CREATE_SQL = [
        'pa_collaboration_session' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `pa_collaboration_session` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `session_key` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `artifact_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `artifact_key` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `engine_name` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `engine_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `base_revision_key` VARCHAR(41) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `base_revision_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `latest_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `opened_by_member_id` BIGINT UNSIGNED NOT NULL,
  `opened_by_account_id` BIGINT UNSIGNED NOT NULL,
  `closed_by_member_id` BIGINT UNSIGNED NULL,
  `closed_by_account_id` BIGINT UNSIGNED NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `closed_at` DATETIME(3) NULL,
  `published_at` DATETIME(3) NULL,
  `published_revision_key` VARCHAR(41) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `published_revision_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `retain_until` DATETIME(3) NULL,
  `active_marker` TINYINT GENERATED ALWAYS AS (CASE WHEN `status` = 'active' THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collaboration_session_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_collaboration_session_key` (`tenant_id`, `session_key`),
  UNIQUE KEY `uk_collaboration_session_active_artifact` (`tenant_id`, `artifact_type`, `artifact_key`, `active_marker`),
  KEY `idx_collaboration_session_artifact` (`tenant_id`, `artifact_type`, `artifact_key`, `created_at`, `id`),
  KEY `idx_collaboration_session_status_expiry` (`tenant_id`, `status`, `expires_at`, `id`),
  KEY `idx_collaboration_session_retention` (`tenant_id`, `retain_until`, `id`),
  CONSTRAINT `fk_collaboration_session_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_session_opened_member` FOREIGN KEY (`tenant_id`, `opened_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_session_closed_member` FOREIGN KEY (`tenant_id`, `closed_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_collaboration_session_key` CHECK (`session_key` REGEXP '^session_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_session_artifact_type` CHECK (`artifact_type` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$'),
  CONSTRAINT `chk_collaboration_session_artifact_key` CHECK (`artifact_key` <> ''),
  CONSTRAINT `chk_collaboration_session_engine` CHECK (`engine_name` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$' AND `engine_version` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$'),
  CONSTRAINT `chk_collaboration_session_base_revision_key` CHECK (`base_revision_key` REGEXP '^revision_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_session_base_revision_digest` CHECK (`base_revision_sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_collaboration_session_latest_sequence` CHECK (`latest_sequence` <= 9223372036854775807),
  CONSTRAINT `chk_collaboration_session_revision` CHECK (`revision` >= 1 AND `revision` <= 9223372036854775807),
  CONSTRAINT `chk_collaboration_session_status` CHECK (`status` IN ('active', 'published', 'closed', 'expired')),
  CONSTRAINT `chk_collaboration_session_actor_shape` CHECK ((`closed_by_member_id` IS NULL AND `closed_by_account_id` IS NULL) OR (`closed_by_member_id` IS NOT NULL AND `closed_by_account_id` IS NOT NULL)),
  CONSTRAINT `chk_collaboration_session_terminal_shape` CHECK (
    (`status` = 'active' AND `closed_by_member_id` IS NULL AND `closed_by_account_id` IS NULL AND `closed_at` IS NULL AND `published_at` IS NULL AND `published_revision_key` IS NULL AND `published_revision_sha256` IS NULL AND `retain_until` IS NULL)
    OR (`status` = 'published' AND `closed_by_member_id` IS NOT NULL AND `closed_by_account_id` IS NOT NULL AND `closed_at` IS NOT NULL AND `published_at` IS NOT NULL AND `published_revision_key` IS NOT NULL AND `published_revision_sha256` IS NOT NULL AND `retain_until` IS NOT NULL)
    OR (`status` IN ('closed', 'expired') AND `closed_at` IS NOT NULL AND `published_at` IS NULL AND `published_revision_key` IS NULL AND `published_revision_sha256` IS NULL AND `retain_until` IS NOT NULL)
  ),
  CONSTRAINT `chk_collaboration_session_published_key` CHECK (`published_revision_key` IS NULL OR `published_revision_key` REGEXP '^revision_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_session_published_digest` CHECK (`published_revision_sha256` IS NULL OR `published_revision_sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_collaboration_session_times` CHECK (`expires_at` > `created_at` AND (`retain_until` IS NULL OR `retain_until` > `closed_at`))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        'pa_collaboration_participant_lease' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `pa_collaboration_participant_lease` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `lease_key` VARCHAR(38) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `client_key` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `capability` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `authorization_basis_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `issued_at` DATETIME(3) NOT NULL,
  `heartbeat_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `revoked_at` DATETIME(3) NULL,
  `active_marker` TINYINT GENERATED ALWAYS AS (CASE WHEN `status` = 'active' THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collaboration_lease_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_collaboration_lease_key` (`tenant_id`, `session_id`, `lease_key`),
  UNIQUE KEY `uk_collaboration_lease_active_client` (`tenant_id`, `session_id`, `client_key`, `active_marker`),
  KEY `idx_collaboration_lease_member` (`tenant_id`, `member_id`, `status`, `expires_at`, `id`),
  KEY `idx_collaboration_lease_session_expiry` (`tenant_id`, `session_id`, `status`, `expires_at`, `id`),
  CONSTRAINT `fk_collaboration_lease_session` FOREIGN KEY (`tenant_id`, `session_id`) REFERENCES `pa_collaboration_session` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_lease_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_collaboration_lease_key` CHECK (`lease_key` REGEXP '^lease_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_lease_client_key` CHECK (`client_key` <> ''),
  CONSTRAINT `chk_collaboration_lease_capability` CHECK (`capability` IN ('read', 'write')),
  CONSTRAINT `chk_collaboration_lease_basis_digest` CHECK (`authorization_basis_sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_collaboration_lease_status` CHECK (`status` IN ('active', 'revoked', 'expired')),
  CONSTRAINT `chk_collaboration_lease_revision` CHECK (`revision` >= 1 AND `revision` <= 9223372036854775807),
  CONSTRAINT `chk_collaboration_lease_terminal_shape` CHECK ((`status` = 'revoked' AND `revoked_at` IS NOT NULL) OR (`status` IN ('active', 'expired') AND `revoked_at` IS NULL)),
  CONSTRAINT `chk_collaboration_lease_times` CHECK (`heartbeat_at` >= `issued_at` AND `expires_at` > `heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        'pa_collaboration_update_envelope' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `pa_collaboration_update_envelope` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `sequence_no` BIGINT UNSIGNED NOT NULL,
  `update_key` VARCHAR(39) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `client_key` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `lease_key` VARCHAR(38) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `engine_name` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `engine_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `byte_length` INT UNSIGNED NOT NULL,
  `update_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `opaque_payload` MEDIUMBLOB NOT NULL,
  `author_member_id` BIGINT UNSIGNED NOT NULL,
  `author_account_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collaboration_update_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_collaboration_update_key` (`tenant_id`, `session_id`, `update_key`),
  UNIQUE KEY `uk_collaboration_update_sequence` (`tenant_id`, `session_id`, `sequence_no`),
  KEY `idx_collaboration_update_session_time` (`tenant_id`, `session_id`, `occurred_at`, `id`),
  CONSTRAINT `fk_collaboration_update_session` FOREIGN KEY (`tenant_id`, `session_id`) REFERENCES `pa_collaboration_session` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_update_lease` FOREIGN KEY (`tenant_id`, `session_id`, `lease_key`) REFERENCES `pa_collaboration_participant_lease` (`tenant_id`, `session_id`, `lease_key`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_update_member` FOREIGN KEY (`tenant_id`, `author_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_collaboration_update_sequence` CHECK (`sequence_no` >= 1 AND `sequence_no` <= 9223372036854775807),
  CONSTRAINT `chk_collaboration_update_key` CHECK (`update_key` REGEXP '^update_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_update_client_key` CHECK (`client_key` <> ''),
  CONSTRAINT `chk_collaboration_update_engine` CHECK (`engine_name` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$' AND `engine_version` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$'),
  CONSTRAINT `chk_collaboration_update_length` CHECK (`byte_length` >= 1 AND `byte_length` <= 262144 AND `byte_length` = OCTET_LENGTH(`opaque_payload`)),
  CONSTRAINT `chk_collaboration_update_digest` CHECK (`update_sha256` REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        'pa_collaboration_snapshot_envelope' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `pa_collaboration_snapshot_envelope` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `snapshot_key` VARCHAR(41) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `covered_sequence` BIGINT UNSIGNED NOT NULL,
  `engine_name` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `engine_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `snapshot_byte_length` INT UNSIGNED NOT NULL,
  `snapshot_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `opaque_snapshot` MEDIUMBLOB NOT NULL,
  `state_vector_byte_length` INT UNSIGNED NOT NULL,
  `state_vector_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `opaque_state_vector` MEDIUMBLOB NOT NULL,
  `author_member_id` BIGINT UNSIGNED NOT NULL,
  `author_account_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `retain_until` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collaboration_snapshot_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_collaboration_snapshot_key` (`tenant_id`, `session_id`, `snapshot_key`),
  KEY `idx_collaboration_snapshot_latest` (`tenant_id`, `session_id`, `covered_sequence`, `id`),
  KEY `idx_collaboration_snapshot_retention` (`tenant_id`, `retain_until`, `id`),
  CONSTRAINT `fk_collaboration_snapshot_session` FOREIGN KEY (`tenant_id`, `session_id`) REFERENCES `pa_collaboration_session` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_collaboration_snapshot_member` FOREIGN KEY (`tenant_id`, `author_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_collaboration_snapshot_key` CHECK (`snapshot_key` REGEXP '^snapshot_[0-9a-f]{32}$'),
  CONSTRAINT `chk_collaboration_snapshot_sequence` CHECK (`covered_sequence` <= 9223372036854775807),
  CONSTRAINT `chk_collaboration_snapshot_engine` CHECK (`engine_name` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$' AND `engine_version` REGEXP '^[a-z][a-z0-9]*([.-][a-z0-9]+)*$'),
  CONSTRAINT `chk_collaboration_snapshot_length` CHECK (`snapshot_byte_length` >= 1 AND `snapshot_byte_length` <= 8388608 AND `snapshot_byte_length` = OCTET_LENGTH(`opaque_snapshot`)),
  CONSTRAINT `chk_collaboration_snapshot_digest` CHECK (`snapshot_sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_collaboration_state_vector_length` CHECK (`state_vector_byte_length` >= 1 AND `state_vector_byte_length` <= 8388608 AND `state_vector_byte_length` = OCTET_LENGTH(`opaque_state_vector`)),
  CONSTRAINT `chk_collaboration_state_vector_digest` CHECK (`state_vector_sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_collaboration_snapshot_retention` CHECK (`retain_until` > `created_at`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
    ];

    private function __construct() {}

    /** @return list<string> */
    public static function tableNames(): array
    {
        return array_keys(self::CREATE_SQL);
    }

    /** @return list<string> */
    public static function createSql(): array
    {
        return array_values(self::CREATE_SQL);
    }

    public static function createTableSql(string $table): string
    {
        return self::CREATE_SQL[$table]
            ?? throw new InvalidArgumentException("Unknown collaboration table: {$table}");
    }

    public static function dropSql(string $table): string
    {
        if (!isset(self::CREATE_SQL[$table])) {
            throw new InvalidArgumentException("Unknown collaboration table: {$table}");
        }

        return "DROP TABLE IF EXISTS `{$table}`";
    }
}
