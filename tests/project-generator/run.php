<?php

declare(strict_types=1);

use PeanutAdmin\ProjectGenerator\GenerationRequest;
use PeanutAdmin\ProjectGenerator\ProjectGenerator;

$root = dirname(__DIR__, 2);
$temporaryRoot = sys_get_temp_dir() . '/peanut-project-generator-' . bin2hex(random_bytes(8));

if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException('Could not create fixture root.');
}

/** @param list<string> $arguments @return array{code: int, stdout: string, stderr: string} */
function runGenerator(string $root, array $arguments): array
{
    $command = array_merge([$root . '/scripts/create-project'], $arguments);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start generator.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'code' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/** @param list<string> $command @return array{code: int, stdout: string, stderr: string} */
function runFixtureCommand(array $command, string $workingDirectory): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start fixture command.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'code' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function gitValue(string $root, string $revision): string
{
    $pipes = [];
    $process = proc_open(['git', '-C', $root, 'rev-parse', $revision], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not inspect generator source identity.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_string($stdout)) {
        throw new RuntimeException('Could not inspect generator source identity: ' . $stderr);
    }

    return trim($stdout);
}

/** @return list<string> */
function validArguments(string $target): array
{
    return [
        '--target', $target,
        '--slug', 'north-star-admin',
        '--display-name', 'North {{ 7 * 7 }} <script>alert(1)</script>',
        '--php-namespace', 'NorthStar\\Admin',
        '--brand', 'Brand {{ ADMIN_CORE_PACKAGE }} <img src=x onerror=alert(1)>',
        '--profile', 'standard-admin',
        '--tenant-client', 'field-console=/api/field/v1/',
        '--tenant-client', 'audit-console=/api/audit/v1/',
        '--admin-client', 'field-console',
        '--feature', 'file-media',
        '--feature', 'settings',
    ];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, string> */
function contentInventory(string $root): array
{
    $result = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            throw new RuntimeException('Generated output must contain regular files only.');
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $result[$relative] = hash_file('sha256', $file->getPathname()) ?: '';
    }
    ksort($result);

    return $result;
}

function removeFixture(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $entries = scandir($path);
    if (!is_array($entries)) {
        throw new RuntimeException("Could not scan fixture: {$path}");
    }
    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeFixture($path . '/' . $entry);
        }
    }
    rmdir($path);
}

try {
    $invalidArgs = validArguments($temporaryRoot . '/invalid-slug');
    $slugIndex = array_search('--slug', $invalidArgs, true);
    assertTrue(is_int($slugIndex), 'Slug argument fixture is invalid.');
    $invalidArgs[$slugIndex + 1] = 'Invalid_Slug';
    $invalid = runGenerator($root, $invalidArgs);
    assertTrue($invalid['code'] !== 0, 'Invalid slug must fail.');
    assertTrue(
        str_contains($invalid['stderr'], 'PROJECT_SLUG_INVALID'),
        'Invalid slug error is unstable: ' . $invalid['stderr'],
    );
    assertTrue(!file_exists($temporaryRoot . '/invalid-slug'), 'Invalid request created a target.');

    $traversal = $temporaryRoot . '/safe/../escaped';
    $result = runGenerator($root, validArguments($traversal));
    assertTrue($result['code'] !== 0, 'Traversal target must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_TARGET_UNSAFE'), 'Traversal error is unstable.');
    assertTrue(!file_exists($temporaryRoot . '/escaped'), 'Traversal target was created.');

    $realTarget = $temporaryRoot . '/real-target';
    mkdir($realTarget, 0700);
    $linkTarget = $temporaryRoot . '/linked-target';
    symlink($realTarget, $linkTarget);
    $result = runGenerator($root, validArguments($linkTarget));
    assertTrue($result['code'] !== 0, 'Symlink target must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_TARGET_UNSAFE'), 'Symlink error is unstable.');
    assertTrue(scandir($realTarget) === ['.', '..'], 'Symlink target content changed.');

    $nonEmpty = $temporaryRoot . '/non-empty';
    mkdir($nonEmpty, 0700);
    file_put_contents($nonEmpty . '/owned.txt', "keep\n");
    $result = runGenerator($root, validArguments($nonEmpty));
    assertTrue($result['code'] !== 0, 'Non-empty target must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_TARGET_NOT_EMPTY'), 'Non-empty error is unstable.');
    assertTrue(file_get_contents($nonEmpty . '/owned.txt') === "keep\n", 'Existing file changed.');

    $sourceTarget = $root . '/generated-inside-source';
    $result = runGenerator($root, validArguments($sourceTarget));
    assertTrue($result['code'] !== 0, 'Source-tree target must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_TARGET_UNSAFE'), 'Source-tree error is unstable.');
    assertTrue(!file_exists($sourceTarget), 'Source-tree target was created.');

    $unknownArgs = validArguments($temporaryRoot . '/unknown-feature');
    array_push($unknownArgs, '--feature', 'remote-marketplace');
    $result = runGenerator($root, $unknownArgs);
    assertTrue($result['code'] !== 0, 'Unknown feature must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_FEATURE_UNKNOWN'), 'Feature error is unstable.');
    assertTrue(!file_exists($temporaryRoot . '/unknown-feature'), 'Unknown feature created a target.');

    $dependencyArgs = validArguments($temporaryRoot . '/missing-feature-dependency');
    array_push($dependencyArgs, '--feature', 'notification-sms');
    $result = runGenerator($root, $dependencyArgs);
    assertTrue($result['code'] !== 0, 'Notification/SMS without Task/Job must fail.');
    assertTrue(
        str_contains($result['stderr'], 'PROJECT_FEATURE_DEPENDENCY_MISSING'),
        'Feature dependency error is unstable.',
    );
    assertTrue(!file_exists($temporaryRoot . '/missing-feature-dependency'), 'Missing feature dependency created a target.');

    $importDependencyArgs = validArguments($temporaryRoot . '/missing-import-export-dependency');
    array_push($importDependencyArgs, '--feature', 'import-export');
    $result = runGenerator($root, $importDependencyArgs);
    assertTrue($result['code'] !== 0, 'Import/Export without Task/Job must fail.');
    assertTrue(
        str_contains($result['stderr'], 'PROJECT_FEATURE_DEPENDENCY_MISSING'),
        'Import/Export dependency error is unstable.',
    );
    assertTrue(!file_exists($temporaryRoot . '/missing-import-export-dependency'), 'Missing Import/Export dependency created a target.');

    $missingAdminArgs = validArguments($temporaryRoot . '/missing-admin');
    $adminIndex = array_search('--admin-client', $missingAdminArgs, true);
    assertTrue(is_int($adminIndex), 'Admin Client argument fixture is invalid.');
    array_splice($missingAdminArgs, $adminIndex, 2);
    $result = runGenerator($root, $missingAdminArgs);
    assertTrue($result['code'] !== 0, 'Multiple Tenant Clients without an explicit admin Client must fail.');
    assertTrue(str_contains($result['stderr'], 'PROJECT_ADMIN_CLIENT_MISSING'), 'Admin Client error is unstable.');
    assertTrue(!file_exists($temporaryRoot . '/missing-admin'), 'Missing admin Client created a target.');

    $dirtySentinel = $root . '/.project-generator-dirty-fixture';
    file_put_contents($dirtySentinel, "fixture\n");
    try {
        $dirtyTarget = $temporaryRoot . '/dirty-source-target';
        $result = runGenerator($root, validArguments($dirtyTarget));
        assertTrue($result['code'] !== 0, 'Dirty Git source must fail.');
        assertTrue(str_contains($result['stderr'], 'PROJECT_SOURCE_DIRTY'), 'Dirty source error is unstable.');
        assertTrue(!file_exists($dirtyTarget), 'Dirty source claimed the target.');
    } finally {
        unlink($dirtySentinel);
    }

    require $root . '/tools/project-generator/src/ProjectGenerator.php';
    $brokenSource = $temporaryRoot . '/broken-source';
    mkdir($brokenSource . '/starter', 0700, true);
    mkdir($brokenSource . '/packages/php', 0700, true);
    mkdir($brokenSource . '/packages/web', 0700, true);
    $emptyTarget = $temporaryRoot . '/cleanup-target';
    mkdir($emptyTarget, 0700);
    $request = new GenerationRequest(
        $emptyTarget,
        'cleanup-admin',
        'Cleanup Admin',
        'Cleanup\\Admin',
        'Cleanup',
        'standard-admin',
        [['key' => 'operations-web', 'api_prefix' => '/api/operations/v1/']],
        'operations-web',
        ['settings'],
    );
    try {
        (new ProjectGenerator($brokenSource))->generate($request);
        throw new RuntimeException('Broken source must fail generation.');
    } catch (Throwable $exception) {
        assertTrue(str_contains($exception->getMessage(), 'PROJECT_SOURCE_INVALID'), 'Template source error is unstable.');
    }
    assertTrue(is_dir($emptyTarget), 'Pre-existing empty target was removed on failure.');
    assertTrue(scandir($emptyTarget) === ['.', '..'], 'Failed generation left partial output.');

    $packageSource = $temporaryRoot . '/package-source';
    $archive = $temporaryRoot . '/candidate.tar';
    $archiveResult = runFixtureCommand(
        ['git', 'archive', '--format=tar', '--output=' . $archive, 'HEAD'],
        $root,
    );
    assertTrue($archiveResult['code'] === 0, 'Could not create candidate archive: ' . $archiveResult['stderr']);
    mkdir($packageSource, 0700);
    $extractResult = runFixtureCommand(['tar', '-xf', $archive, '-C', $packageSource], $root);
    assertTrue($extractResult['code'] === 0, 'Could not extract candidate archive: ' . $extractResult['stderr']);
    assertTrue(!file_exists($packageSource . '/.git'), 'Candidate archive contains Git state.');
    $packageIdentity = json_decode(
        (string) file_get_contents($packageSource . '/tools/project-generator/package-identity.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    assertTrue(($packageIdentity['commit'] ?? null) === gitValue($root, 'HEAD^{commit}'), 'Archive commit identity was not expanded.');
    assertTrue(($packageIdentity['tree'] ?? null) === gitValue($root, 'HEAD^{tree}'), 'Archive tree identity was not expanded.');
    $packageTarget = $temporaryRoot . '/package-output';
    $packageResult = runGenerator($packageSource, validArguments($packageTarget));
    assertTrue($packageResult['code'] === 0, 'Archived generator failed: ' . $packageResult['stderr']);
    assertTrue(is_file($packageTarget . '/peanut-project.json'), 'Validated candidate archive did not generate.');
    $packageMetadata = json_decode(
        (string) file_get_contents($packageTarget . '/peanut-project.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    assertTrue(
        ($packageMetadata['peanut_admin']['input_commit'] ?? null) === ($packageIdentity['commit'] ?? null),
        'No-Git package commit identity drifted.',
    );
    assertTrue(
        ($packageMetadata['peanut_admin']['input_tree'] ?? null) === ($packageIdentity['tree'] ?? null),
        'No-Git package tree identity drifted.',
    );

    file_put_contents($packageSource . '/starter/frontend/src/App.vue', "\nsource drift\n", FILE_APPEND);
    $driftTarget = $temporaryRoot . '/drift-output';
    $driftResult = runGenerator($packageSource, validArguments($driftTarget));
    assertTrue($driftResult['code'] !== 0, 'Drifted no-Git source must fail generation.');
    assertTrue(str_contains($driftResult['stderr'], 'PROJECT_SOURCE_DRIFT'), 'Packaged source drift error is unstable.');
    assertTrue(!file_exists($driftTarget), 'Drifted no-Git source claimed the target.');

    $first = $temporaryRoot . '/first';
    $second = $temporaryRoot . '/second';
    $firstResult = runGenerator($root, validArguments($first));
    $secondResult = runGenerator($root, validArguments($second));
    assertTrue($firstResult['code'] === 0, 'First generation failed: ' . $firstResult['stderr']);
    assertTrue($secondResult['code'] === 0, 'Second generation failed: ' . $secondResult['stderr']);
    assertTrue(contentInventory($first) === contentInventory($second), 'Same inputs are not byte deterministic.');

    $collisionTarget = $temporaryRoot . '/legacy-key-collision';
    $collisionArguments = validArguments($collisionTarget);
    foreach ($collisionArguments as $index => $argument) {
        $collisionArguments[$index] = match ($argument) {
            'field-console=/api/field/v1/' => 'reporting-web=/api/reporting/v1/',
            'audit-console=/api/audit/v1/' => 'operations-web=/api/operations/v1/',
            'field-console' => 'reporting-web',
            default => $argument,
        };
    }
    $collisionResult = runGenerator($root, $collisionArguments);
    assertTrue($collisionResult['code'] === 0, 'Legacy Client key collision generation failed.');
    $collisionFixture = (string) file_get_contents($collisionTarget . '/backend/tests/auth-clients.php');
    assertTrue(str_contains($collisionFixture, "create('reporting-web')"), 'Admin legacy Client key collided.');
    assertTrue(str_contains($collisionFixture, "create('operations-web')"), 'Secondary legacy Client key collided.');

    $metadata = json_decode((string) file_get_contents($first . '/peanut-project.json'), true, 512, JSON_THROW_ON_ERROR);
    assertTrue(($metadata['schema_version'] ?? null) === 1, 'Generator schema is missing.');
    $headCommit = gitValue($root, 'HEAD^{commit}');
    $headTree = gitValue($root, 'HEAD^{tree}');
    assertTrue(preg_match('/^[0-9a-f]{40}$/D', $headCommit) === 1, 'Git HEAD commit identity is unavailable.');
    assertTrue(preg_match('/^[0-9a-f]{40}$/D', $headTree) === 1, 'Git HEAD tree identity is unavailable.');
    assertTrue(($metadata['peanut_admin']['input_commit'] ?? null) === $headCommit, 'Input commit drifted.');
    assertTrue(($metadata['peanut_admin']['input_tree'] ?? null) === $headTree, 'Input tree drifted.');
    assertTrue(($metadata['project']['features'] ?? null) === ['settings', 'file-media'], 'Features are not canonical.');
    assertTrue(($metadata['secrets']['embedded'] ?? null) === false, 'Metadata claims embedded secrets.');
    assertTrue(!file_exists($first . '/.peanut-project-generation'), 'Ownership marker leaked into output.');

    $all = implode("\n", array_map(
        static fn(string $path): string => (string) file_get_contents($first . '/' . $path),
        array_keys(contentInventory($first)),
    ));
    assertTrue(!str_contains($all, $root), 'Generated output contains the source absolute path.');
    $app = (string) file_get_contents($first . '/frontend/src/App.vue');
    assertTrue(str_contains($app, 'const projectBrand = "Brand {{ ADMIN_CORE_PACKAGE }} \\u003Cimg'), 'Brand constant was not encoded.');
    assertTrue(str_contains($app, 'v-text="projectBrand"'), 'Brand is not bound as text.');
    assertTrue(str_contains($app, 'v-text="projectDisplayName"'), 'Display name is not bound as text.');
    assertTrue(!str_contains($app, '<img src=x') && !str_contains($app, '<script>'), 'HTML-like project labels reached the Vue template.');
    $authConfig = require $first . '/backend/config/auth.php';
    assertTrue(($authConfig['admin_client_key'] ?? null) === 'field-console', 'Admin Client was not rendered.');
    assertTrue(($authConfig['tenant_clients'][0]['api_prefix'] ?? null) === '/api/field/v1/', 'Tenant Client structure was lost.');
    assertTrue(str_contains((string) file_get_contents($first . '/backend/config/modules.php'), 'peanut.settings'), 'Selected Settings Module is absent.');
    $moduleConfig = require $first . '/backend/config/modules.php';
    assertTrue(($moduleConfig['kernel_version'] ?? null) === '1.0.0', 'Kernel compatibility version was not rendered.');
    assertTrue(!str_contains((string) file_get_contents($first . '/backend/config/modules.php'), 'peanut.reference-codes'), 'Unselected Module was enabled.');
    assertTrue(str_contains((string) file_get_contents($first . '/backend/config/modules.php'), 'peanut.ops-console.page'), 'Always-on Ops Console was removed.');
    assertTrue(is_file($first . '/frontend/src/modules/peanut-ops-console.ts'), 'Always-on Ops Console Host is absent.');
    assertTrue(!is_dir($first . '/backend/src/Modules/Peanut/IntegrationSecurity'), 'Unselected Integration Security Module was retained.');
    assertTrue(!is_file($first . '/frontend/src/modules/peanut-integration-security.ts'), 'Unselected Integration Security Host was retained.');
    $fileMenus = json_decode(
        (string) file_get_contents($first . '/backend/src/Modules/Peanut/FileMedia/Resources/menus.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    assertTrue(($fileMenus[0]['client_keys'] ?? null) === ['field-console'], 'Selected Module menu did not use the admin Client.');
    $authFixture = (string) file_get_contents($first . '/backend/tests/auth-clients.php');
    assertTrue(str_contains($authFixture, "create('field-console')"), 'Primary auth fixture Client was not adapted.');
    assertTrue(str_contains($authFixture, "create('audit-console')"), 'Secondary auth fixture Client was not adapted.');
    assertTrue(!str_contains($authFixture, "create('operations-web')") && !str_contains($authFixture, "create('reporting-web')"), 'Legacy auth fixture Client leaked.');

    $environment = (string) file_get_contents($first . '/.env.example');
    foreach (['PASSWORD', 'SECRET', 'TOKEN', 'KEY'] as $needle) {
        foreach (preg_split('/\R/', $environment) ?: [] as $line) {
            if (str_contains($line, $needle) && !str_ends_with($line, '=')) {
                throw new RuntimeException("Secret template contains a value: {$line}");
            }
        }
    }

    fwrite(STDOUT, "Project generator fixture: OK\n");
} finally {
    removeFixture($temporaryRoot);
}
