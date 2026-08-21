<?php

declare(strict_types=1);

use PeanutAdmin\FileMedia\Database\Schema;
use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateFileObjects extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'peanut.file-media';
    }

    public static function ownedTables(): array
    {
        return ['pa_file_object'];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(Schema::createSql('pa_file_object'));
    }

    public function down(): void
    {
        $this->execute(Schema::dropSql('pa_file_object'));
    }
}
