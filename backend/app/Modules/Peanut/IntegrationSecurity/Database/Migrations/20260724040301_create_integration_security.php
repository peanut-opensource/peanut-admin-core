<?php

declare(strict_types=1);

use PeanutAdmin\IntegrationSecurity\Database\Schema;
use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateIntegrationSecurity extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'peanut.integration-security';
    }
    public static function ownedTables(): array
    {
        return Schema::tableNames();
    }
    public static function reversible(): bool
    {
        return false;
    }
    public function up(): void
    {
        foreach (Schema::tableNames() as $table) {
            $this->execute(Schema::createSql($table));
        }
    }
    public function down(): void {}
}
