<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/src/Modules/Peanut/ImportExport/module.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['key'] ?? null) !== 'peanut.import-export'
    || ($manifest['dependencies'] ?? null) !== [
        ['module_key' => 'peanut.file-media', 'version' => '^0.1'],
        ['module_key' => 'peanut.task-job', 'version' => '^0.1'],
    ]
    || ($manifest['backend']['provider'] ?? null) !== 'ExampleHost\\App\\Modules\\Peanut\\ImportExport\\ModuleProvider'
    || !is_file($root . '/src/Modules/Peanut/ImportExport/Database/Migrations/20260724040101_create_import_export.php')) {
    throw new RuntimeException('Internal starter Import/Export integration is incomplete.');
}

fwrite(STDOUT, "Internal starter Import/Export integration: OK\n");
