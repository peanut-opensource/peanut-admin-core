<?php

declare(strict_types=1);

use PeanutAdmin\App\Modules\Example\Reference\Database\Schema;
use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateExampleReferences extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'example.reference';
    }

    public static function ownedTables(): array
    {
        return ['pa_example_reference_item', 'pa_example_reference_scope'];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(Schema::createItem());
        $this->execute(Schema::createScope());
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `pa_example_reference_scope`');
        $this->execute('DROP TABLE IF EXISTS `pa_example_reference_item`');
    }
}
