<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Migration\OwnedMigration;
use PeanutAdmin\ReferenceCodes\Database\Schema;
use think\migration\Migrator;

final class CreateReferenceCodeEntries extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'peanut.reference-codes';
    }

    public static function ownedTables(): array
    {
        return ['pa_reference_code_entry'];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(Schema::createSql('pa_reference_code_entry'));
    }

    public function down(): void
    {
        $this->execute(Schema::dropSql('pa_reference_code_entry'));
    }
}
