<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Migration\OwnedMigration;
use PeanutAdmin\NotificationSms\Database\Schema;
use think\migration\Migrator;

final class CreateNotifications extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string { return 'peanut.notification-sms'; }
    public static function ownedTables(): array { return Schema::tableNames(); }
    public static function reversible(): bool { return true; }

    public function up(): void
    {
        foreach (Schema::tableNames() as $table) $this->execute(Schema::createSql($table));
    }

    public function down(): void
    {
        foreach (array_reverse(Schema::tableNames()) as $table) $this->execute(Schema::dropSql($table));
    }
}
