<?php

declare(strict_types=1);

use PeanutAdmin\App\Modules\Example\WorkItem\Database\Schema;
use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateExampleWorkItems extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'example.work-item';
    }

    public static function ownedTables(): array
    {
        return [
            'pa_example_work_item',
            'pa_example_work_item_view_policy',
            'pa_example_work_item_policy_publication',
        ];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        foreach (Schema::createStatements() as $statement) {
            $this->execute($statement);
        }
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `pa_example_work_item_policy_publication`');
        $this->execute('DROP TABLE IF EXISTS `pa_example_work_item_view_policy`');
        $this->execute('DROP TABLE IF EXISTS `pa_example_work_item`');
    }
}
