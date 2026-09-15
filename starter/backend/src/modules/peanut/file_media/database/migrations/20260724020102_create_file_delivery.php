<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateFileDelivery extends Migrator implements OwnedMigration
{
    private const TABLES = ['pa_file_delivery_policy', 'pa_file_image_metadata', 'pa_file_image_variant', 'pa_file_delivery_nonce'];

    public static function moduleKey(): string { return 'peanut.file-media'; }
    public static function ownedTables(): array { return self::TABLES; }
    public static function reversible(): bool { return true; }

    public function up(): void
    {
        $this->execute('ALTER TABLE `pa_file_object` ADD UNIQUE KEY `uk_file_object_tenant_id` (`tenant_id`,`id`)');
        $this->execute("CREATE TABLE `pa_file_delivery_policy` (`tenant_id` BIGINT UNSIGNED NOT NULL,`file_object_id` BIGINT UNSIGNED NOT NULL,`visibility` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private',`revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,`updated_at` DATETIME(3) NOT NULL,PRIMARY KEY (`tenant_id`,`file_object_id`),CONSTRAINT `fk_file_delivery_object` FOREIGN KEY (`tenant_id`,`file_object_id`) REFERENCES `pa_file_object` (`tenant_id`,`id`) ON DELETE RESTRICT,CONSTRAINT `chk_file_delivery_visibility` CHECK (`visibility` IN ('private','public')),CONSTRAINT `chk_file_delivery_revision` CHECK (`revision` >= 1)) ENGINE=InnoDB");
        $this->execute("CREATE TABLE `pa_file_image_metadata` (`tenant_id` BIGINT UNSIGNED NOT NULL,`file_object_id` BIGINT UNSIGNED NOT NULL,`width` INT UNSIGNED NOT NULL,`height` INT UNSIGNED NOT NULL,`media_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`created_at` DATETIME(3) NOT NULL,PRIMARY KEY (`tenant_id`,`file_object_id`),CONSTRAINT `fk_file_image_object` FOREIGN KEY (`tenant_id`,`file_object_id`) REFERENCES `pa_file_object` (`tenant_id`,`id`) ON DELETE RESTRICT,CONSTRAINT `chk_file_image_dimensions` CHECK (`width` BETWEEN 1 AND 50000 AND `height` BETWEEN 1 AND 50000 AND `width` * `height` <= 100000000),CONSTRAINT `chk_file_image_type` CHECK (`media_type` IN ('image/jpeg','image/png'))) ENGINE=InnoDB");
        $this->execute("CREATE TABLE `pa_file_image_variant` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` BIGINT UNSIGNED NOT NULL,`file_object_id` BIGINT UNSIGNED NOT NULL,`variant_key` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`storage_provider_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`storage_key` VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`width` INT UNSIGNED NOT NULL,`height` INT UNSIGNED NOT NULL,`media_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`size_bytes` BIGINT UNSIGNED NOT NULL,`sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,`created_at` DATETIME(3) NOT NULL,`updated_at` DATETIME(3) NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `uk_file_variant` (`tenant_id`,`file_object_id`,`variant_key`),CONSTRAINT `fk_file_variant_object` FOREIGN KEY (`tenant_id`,`file_object_id`) REFERENCES `pa_file_object` (`tenant_id`,`id`) ON DELETE RESTRICT,CONSTRAINT `chk_file_variant_key` CHECK (`variant_key` REGEXP '^[a-z][a-z0-9-]{0,31}$'),CONSTRAINT `chk_file_variant_status` CHECK (`status` IN ('ready','failed')),CONSTRAINT `chk_file_variant_type` CHECK (`media_type` IN ('image/jpeg','image/png')),CONSTRAINT `chk_file_variant_dimensions` CHECK (`width` BETWEEN 1 AND 4096 AND `height` BETWEEN 1 AND 4096),CONSTRAINT `chk_file_variant_sha` CHECK (`sha256` REGEXP '^[0-9a-f]{64}$'),CONSTRAINT `chk_file_variant_revision` CHECK (`revision` >= 1)) ENGINE=InnoDB");
        $this->execute("CREATE TABLE `pa_file_delivery_nonce` (`tenant_id` BIGINT UNSIGNED NOT NULL,`token_id_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`expires_at` DATETIME(3) NOT NULL,`consumed_at` DATETIME(3) NOT NULL,PRIMARY KEY (`tenant_id`,`token_id_hash`),KEY `idx_file_delivery_nonce_expiry` (`expires_at`),CONSTRAINT `fk_file_delivery_nonce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,CONSTRAINT `chk_file_delivery_nonce_hash` CHECK (`token_id_hash` REGEXP '^[0-9a-f]{64}$')) ENGINE=InnoDB");
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) $this->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        $this->execute('ALTER TABLE `pa_file_object` DROP INDEX `uk_file_object_tenant_id`');
    }
}
