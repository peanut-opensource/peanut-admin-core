<?php

declare(strict_types=1);

use PeanutAdmin\App\Modules\Example\Target\Database\Schema;
use PeanutAdmin\Kernel\Migration\OwnedMigration;
use think\migration\Migrator;

final class CreateExampleTargets extends Migrator implements OwnedMigration
{
    public static function moduleKey(): string
    {
        return 'example.target';
    }

    public static function ownedTables(): array
    {
        return ['pa_example_project', 'pa_example_queue'];
    }

    public static function reversible(): bool
    {
        return true;
    }

    public function up(): void
    {
        $this->execute(Schema::createProject());
        $this->execute(Schema::createQueue());
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `pa_example_queue`');
        $this->execute('DROP TABLE IF EXISTS `pa_example_project`');
    }
}
