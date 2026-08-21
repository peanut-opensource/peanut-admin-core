<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Migration\OwnedMigration;
use PeanutAdmin\Settings\Database\Schema;
use think\migration\Migrator;

final class CreateSettingDefinitions extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'peanut.settings';
    }

    public static function ownedTables(): array
    {
        return ['pa_setting_definition'];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(Schema::createSql('pa_setting_definition'));
    }

    public function down(): void
    {
        $this->execute(Schema::dropSql('pa_setting_definition'));
    }
}
