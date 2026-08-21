<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$target = sys_get_temp_dir() . '/peanut-project-static-' . bin2hex(random_bytes(8));
$lintPhp = getenv('PEANUT_PHP83') ?: PHP_BINARY;
$arguments = [
    $root . '/scripts/create-project',
    '--target', $target,
    '--slug', 'static-admin',
    '--display-name', 'Static Admin',
    '--php-namespace', 'StaticProject\\Admin',
    '--brand', 'Static',
    '--profile', 'standard-admin',
    '--tenant-client', 'field-console=/api/field/v1/',
    '--tenant-client', 'audit-console=/api/audit/v1/',
    '--admin-client', 'field-console',
    '--feature', 'settings',
    '--feature', 'reference-codes',
    '--feature', 'file-media',
    '--feature', 'task-job',
    '--feature', 'notification-sms',
    '--feature', 'import-export',
    '--feature', 'integration-security',
];

function removeStaticFixture(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeStaticFixture($path . '/' . $entry);
        }
    }
    rmdir($path);
}

/** @param list<string> $command */
function runStaticCommand(array $command, string $workingDirectory): void
{
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start static check: ' . implode(' ', $command));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException(
            'Static check failed: ' . implode(' ', $command) . "\n" . $stdout . $stderr,
        );
    }
}

try {
    $version = trim((string) shell_exec(escapeshellarg($lintPhp) . ' -r ' . escapeshellarg('echo PHP_VERSION;')));
    if (version_compare($version, '8.3.0', '<')) {
        throw new RuntimeException('Set PEANUT_PHP83 to a PHP 8.3+ binary for generated project lint.');
    }
    $pipes = [];
    $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start generator.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException('Generation failed: ' . $stderr);
    }

    foreach ([
        'backend/composer.json',
        'backend/composer.lock',
        'frontend/package.json',
        'pnpm-lock.yaml',
        'backend/config/auth.php',
        'backend/config/modules.php',
        'peanut-project.json',
    ] as $path) {
        if (!is_file($target . '/' . $path)) {
            throw new RuntimeException("Generated contract file is missing: {$path}");
        }
    }

    $jsonFiles = [];
    $phpFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($target) + 1);
        if ($file->getExtension() === 'json') {
            $jsonFiles[] = $file->getPathname();
        }
        if ($file->getExtension() === 'php' && str_starts_with($relative, 'backend/')) {
            $phpFiles[] = $file->getPathname();
        }
    }
    foreach ($jsonFiles as $file) {
        json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }
    foreach ($phpFiles as $file) {
        $lint = proc_open([$lintPhp, '-l', $file], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $lintPipes);
        if (!is_resource($lint)) {
            throw new RuntimeException("Could not lint generated PHP: {$file}");
        }
        fclose($lintPipes[0]);
        $lintOut = stream_get_contents($lintPipes[1]);
        $lintError = stream_get_contents($lintPipes[2]);
        fclose($lintPipes[1]);
        fclose($lintPipes[2]);
        if (proc_close($lint) !== 0) {
            throw new RuntimeException("Generated PHP is invalid: {$file}: {$lintOut}{$lintError}");
        }
    }

    $composer = json_decode((string) file_get_contents($target . '/backend/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($composer['autoload']['psr-4']['StaticProject\\Admin\\'] ?? null) !== 'src/') {
        throw new RuntimeException('Generated PHP namespace is not wired.');
    }
    $metadata = json_decode((string) file_get_contents($target . '/peanut-project.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($metadata['project']['profile'] ?? null) !== 'standard-admin') {
        throw new RuntimeException('Generated profile is invalid.');
    }
    if (($metadata['project']['tenant_clients'][0]['key'] ?? null) !== 'field-console'
        || ($metadata['project']['admin_client_key'] ?? null) !== 'field-console') {
        throw new RuntimeException('Generated Tenant Client is invalid.');
    }
    foreach (['Settings', 'ReferenceCodes', 'FileMedia', 'TaskJob', 'NotificationSms', 'ImportExport', 'IntegrationSecurity'] as $module) {
        $menus = json_decode(
            (string) file_get_contents($target . "/backend/src/Modules/Peanut/{$module}/Resources/menus.json"),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (($menus[0]['client_keys'] ?? null) !== ['field-console']) {
            throw new RuntimeException("Generated {$module} menu is not bound to the admin Client.");
        }
    }
    $moduleConfig = (string) file_get_contents($target . '/backend/config/modules.php');
    $moduleConfigValues = require $target . '/backend/config/modules.php';
    if (($moduleConfigValues['kernel_version'] ?? null) !== '1.0.0') {
        throw new RuntimeException('Generated Host Kernel compatibility version is invalid.');
    }
    if (!str_contains($moduleConfig, 'peanut.ops-console.page')
        || !is_file($target . '/frontend/src/modules/peanut-ops-console.ts')) {
        throw new RuntimeException('Generated standard-admin Host is missing the always-on Ops Console.');
    }

    $autoload = getenv('PEANUT_PROJECT_GENERATOR_AUTOLOAD') ?: $root . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Set PEANUT_PROJECT_GENERATOR_AUTOLOAD to an existing repository vendor/autoload.php.');
    }
    $autoloadDirectory = $target . '/backend/vendor';
    if (!mkdir($autoloadDirectory, 0700, true) && !is_dir($autoloadDirectory)) {
        throw new RuntimeException('Could not create the generated Host smoke autoload directory.');
    }
    $autoloadLiteral = var_export($autoload, true);
    $generatedRootLiteral = var_export($target . '/backend/src/', true);
    file_put_contents(
        $autoloadDirectory . '/autoload.php',
        "<?php\n\n\$loader = require {$autoloadLiteral};\n"
        . "\$loader->addPsr4('StaticProject\\\\Admin\\\\', {$generatedRootLiteral});\n"
        . "return \$loader;\n",
    );
    runStaticCommand([$lintPhp, $target . '/backend/tests/smoke.php'], $target);
    if (file_exists($target . '/.git')) {
        throw new RuntimeException('Generator created Git state.');
    }
    if (file_exists($target . '/.peanut-project-generation')) {
        throw new RuntimeException('Generator ownership marker leaked into output.');
    }
    foreach ([
        [PHP_BINARY, '-l', $root . '/tools/project-generator/src/ProjectGenerator.php'],
        [PHP_BINARY, '-l', $root . '/tools/project-generator/create-project.php'],
        [PHP_BINARY, '-l', $root . '/tests/project-generator/run.php'],
        ['bash', '-n', $root . '/scripts/create-project'],
        ['git', 'diff', '--check'],
    ] as $command) {
        runStaticCommand($command, $root);
    }

    fwrite(STDOUT, "Project generator static contract: OK\n");
} finally {
    removeStaticFixture($target);
}
