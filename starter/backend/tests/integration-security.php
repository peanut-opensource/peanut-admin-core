<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/src/modules/peanut/integration_security/module.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['key'] ?? null) !== 'peanut.integration-security'
    || ($manifest['backend']['provider'] ?? null) !== 'ExampleHost\\App\\modules\\peanut\\integration_security\\ModuleProvider'
    || ($manifest['tenant']['enableable'] ?? null) !== true
    || !is_file($root . '/src/modules/peanut/integration_security/database/migrations/20260724040301_create_integration_security.php')) {
    throw new RuntimeException('Internal starter Integration Security integration is incomplete.');
}

fwrite(STDOUT, "Internal starter Integration Security integration: OK\n");
