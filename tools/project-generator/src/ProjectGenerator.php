<?php

declare(strict_types=1);

namespace PeanutAdmin\ProjectGenerator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class ProjectGeneratorException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $detail)
    {
        parent::__construct($errorCode . ': ' . $detail);
    }
}

final class GenerationRequest
{
    private const FEATURES = ['settings', 'reference-codes', 'file-media', 'task-job', 'notification-sms', 'import-export', 'integration-security'];

    /**
     * @param list<array{key: string, api_prefix: string}> $tenantClients
     * @param list<string> $features
     */
    public function __construct(
        public readonly string $target,
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $phpNamespace,
        public readonly string $brand,
        public readonly string $profile,
        public readonly array $tenantClients,
        public readonly string $adminClientKey,
        public readonly array $features,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $slug) !== 1 || strlen($slug) > 63) {
            throw new ProjectGeneratorException('PROJECT_SLUG_INVALID', 'Use a lowercase hyphenated slug up to 63 characters.');
        }
        self::assertLabel($displayName, 'PROJECT_DISPLAY_NAME_INVALID', 120);
        self::assertLabel($brand, 'PROJECT_BRAND_INVALID', 80);
        if (preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)+$/D', $phpNamespace) !== 1
            || strlen($phpNamespace) > 160) {
            throw new ProjectGeneratorException('PROJECT_NAMESPACE_INVALID', 'Use a multi-segment PSR-4 namespace.');
        }
        if ($profile !== 'standard-admin') {
            throw new ProjectGeneratorException('PROJECT_PROFILE_UNKNOWN', 'Only standard-admin is available in this generator schema.');
        }
        if ($tenantClients === [] || count($tenantClients) > 8) {
            throw new ProjectGeneratorException('PROJECT_TENANT_CLIENT_INVALID', 'Define between one and eight Tenant Clients.');
        }
        $keys = [];
        $prefixes = [];
        foreach ($tenantClients as $client) {
            $key = $client['key'] ?? '';
            $prefix = $client['api_prefix'] ?? '';
            if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $key) !== 1 || strlen($key) > 64) {
                throw new ProjectGeneratorException('PROJECT_TENANT_CLIENT_INVALID', 'Tenant Client key is invalid.');
            }
            if (preg_match('~^/api(?:/[a-z][a-z0-9-]*)+/v[1-9][0-9]*/$~D', $prefix) !== 1
                || str_contains($prefix, '//') || str_contains($prefix, '..')) {
                throw new ProjectGeneratorException('PROJECT_TENANT_CLIENT_INVALID', 'Tenant Client API prefix is invalid.');
            }
            if (isset($keys[$key]) || isset($prefixes[$prefix])) {
                throw new ProjectGeneratorException('PROJECT_TENANT_CLIENT_INVALID', 'Tenant Client keys and prefixes must be unique.');
            }
            $keys[$key] = true;
            $prefixes[$prefix] = true;
        }
        if (!isset($keys[$adminClientKey])) {
            throw new ProjectGeneratorException(
                'PROJECT_ADMIN_CLIENT_INVALID',
                'The admin Client must name one of the declared Tenant Clients.',
            );
        }
        $canonical = [];
        foreach (self::FEATURES as $feature) {
            if (in_array($feature, $features, true)) {
                $canonical[] = $feature;
            }
        }
        foreach ($features as $feature) {
            if (!in_array($feature, self::FEATURES, true)) {
                throw new ProjectGeneratorException('PROJECT_FEATURE_UNKNOWN', "Unknown first-party feature: {$feature}.");
            }
        }
        if (count(array_unique($features)) !== count($features)) {
            throw new ProjectGeneratorException('PROJECT_FEATURE_UNKNOWN', 'A first-party feature was selected more than once.');
        }
        if ($canonical !== $features) {
            throw new ProjectGeneratorException('PROJECT_FEATURE_ORDER_INVALID', 'Features must use canonical order.');
        }
        if (in_array('notification-sms', $features, true)
            && (!in_array('task-job', $features, true) || !in_array('file-media', $features, true))) {
            throw new ProjectGeneratorException(
                'PROJECT_FEATURE_DEPENDENCY_MISSING',
                'Notification/SMS requires File/Media and Task/Job.',
            );
        }
        if (in_array('import-export', $features, true)
            && (!in_array('task-job', $features, true) || !in_array('file-media', $features, true))) {
            throw new ProjectGeneratorException('PROJECT_FEATURE_DEPENDENCY_MISSING', 'Import/Export requires File/Media and Task/Job.');
        }
        if ($target === '' || str_contains($target, "\0")) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target path is empty or invalid.');
        }
    }

    private static function assertLabel(string $value, string $errorCode, int $maximum): void
    {
        if ($value === '' || trim($value) !== $value || strlen($value) > $maximum
            || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ProjectGeneratorException($errorCode, 'Value is empty, malformed, padded, or too long.');
        }
    }
}

final class ProjectGenerator
{
    private const MARKER = '.peanut-project-generation';

    /** @var list<array{0: string, 1: string}> */
    private const PACKAGE_SNAPSHOTS = [
        ['packages/php', 'composer.json'],
        ['packages/web', 'package.json'],
    ];

    /** @var array<string, list<string>> */
    private const PACKAGE_MODULES = [
        'packages/php' => [
            'kernel',
            'data-permission',
            'testing',
            'settings',
            'reference-codes',
            'file-media',
            'task-job',
            'notification-sms',
            'import-export',
            'ops-console',
            'integration-security',
        ],
        'packages/web' => [
            'admin-core',
            'admin-shell',
            'testing',
            'settings',
            'reference-codes',
            'file-media',
            'task-job',
            'notification-sms',
            'import-export',
            'ops-console',
            'integration-security',
        ],
    ];

    /** @var array<string, array{backend_root: string, frontend_component: string, frontend_host: string, backend_test: string, frontend_test: string}> */
    private const FEATURES = [
        'settings' => [
            'backend_root' => 'backend/src/Modules/Peanut/Settings',
            'frontend_component' => 'peanut.settings.page',
            'frontend_host' => 'frontend/src/modules/peanut-settings.ts',
            'backend_test' => 'backend/tests/settings.php',
            'frontend_test' => 'frontend/tests/settings.spec.ts',
        ],
        'reference-codes' => [
            'backend_root' => 'backend/src/Modules/Peanut/ReferenceCodes',
            'frontend_component' => 'peanut.reference-codes.page',
            'frontend_host' => 'frontend/src/modules/peanut-reference-codes.ts',
            'backend_test' => 'backend/tests/reference-codes.php',
            'frontend_test' => 'frontend/tests/reference-codes.spec.ts',
        ],
        'file-media' => [
            'backend_root' => 'backend/src/Modules/Peanut/FileMedia',
            'frontend_component' => 'peanut.file-media.page',
            'frontend_host' => 'frontend/src/modules/peanut-file-media.ts',
            'backend_test' => 'backend/tests/file-media.php',
            'frontend_test' => 'frontend/tests/file-media.spec.ts',
        ],
        'task-job' => [
            'backend_root' => 'backend/src/Modules/Peanut/TaskJob',
            'frontend_component' => 'peanut.task-job.page',
            'frontend_host' => 'frontend/src/modules/peanut-task-job.ts',
            'backend_test' => 'backend/tests/task-job.php',
            'frontend_test' => 'frontend/tests/task-job.spec.ts',
        ],
        'notification-sms' => [
            'backend_root' => 'backend/src/Modules/Peanut/NotificationSms',
            'frontend_component' => 'peanut.notification-sms.page',
            'frontend_host' => 'frontend/src/modules/peanut-notification-sms.ts',
            'backend_test' => 'backend/tests/notification-sms.php',
            'frontend_test' => 'frontend/tests/notification-sms.spec.ts',
        ],
        'import-export' => [
            'backend_root' => 'backend/src/Modules/Peanut/ImportExport',
            'frontend_component' => 'peanut.import-export.page',
            'frontend_host' => 'frontend/src/modules/peanut-import-export.ts',
            'backend_test' => 'backend/tests/import-export.php',
            'frontend_test' => 'frontend/tests/import-export.spec.ts',
        ],
        'integration-security' => [
            'backend_root' => 'backend/src/Modules/Peanut/IntegrationSecurity',
            'frontend_component' => 'peanut.integration-security.page',
            'frontend_host' => 'frontend/src/modules/peanut-integration-security.ts',
            'backend_test' => 'backend/tests/integration-security.php',
            'frontend_test' => 'frontend/tests/integration-security.spec.ts',
        ],
    ];

    /** @var array<string, list<string>> */
    private const FEATURE_DEPENDENCIES = [
        'notification-sms' => ['file-media', 'task-job'],
        'import-export' => ['file-media', 'task-job'],
    ];

    private string $sourceRoot;

    public function __construct(string $sourceRoot)
    {
        $physical = realpath($sourceRoot);
        if (!is_string($physical) || !is_dir($physical)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Generator source root is unavailable.');
        }
        $this->sourceRoot = rtrim($physical, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    public function generate(GenerationRequest $request): array
    {
        $sourceIdentity = $this->validateSource();
        [$target, $targetCreated] = $this->prepareTarget($request->target);
        $owner = bin2hex(random_bytes(24));
        $marker = $target . '/' . self::MARKER;
        $markerHandle = @fopen($marker, 'x');
        if (!is_resource($markerHandle)) {
            if ($targetCreated) {
                @rmdir($target);
            }
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Could not claim the target directory.');
        }
        fwrite($markerHandle, $owner . "\n");
        fclose($markerHandle);
        $claimedEntries = scandir($target);
        if (!is_array($claimedEntries)
            || array_values(array_diff($claimedEntries, ['.', '..', self::MARKER])) !== []) {
            @unlink($marker);
            if ($targetCreated) {
                @rmdir($target);
            }
            throw new ProjectGeneratorException('PROJECT_TARGET_NOT_EMPTY', 'Target changed while it was being claimed.');
        }

        try {
            $this->copyTree($this->sourceRoot . '/starter', $target);
            $this->copyPackageSnapshots($target);
            $this->selectFeatures($target, $request->features);
            $this->replaceNamespaces($target, $request->phpNamespace);
            $this->writeProjectFiles($target, $request, $sourceIdentity);
            $this->assertGeneratedBoundary($target);
            if (!@unlink($marker)) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not release target ownership marker.');
            }

            return $this->metadata($request, $sourceIdentity);
        } catch (Throwable $exception) {
            $this->cleanupOwnedTarget($target, $marker, $owner, $targetCreated);
            if ($exception instanceof ProjectGeneratorException) {
                throw $exception;
            }
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Generation stopped without publishing a partial project.');
        }
    }

    /** @return array{input_commit: string, input_tree: string} */
    private function validateSource(): array
    {
        $baselinePath = $this->sourceRoot . '/tools/project-generator/source-baseline.json';
        try {
            $baseline = is_file($baselinePath)
                ? json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR)
                : null;
        } catch (Throwable) {
            $baseline = null;
        }
        $packageIdentityPath = $this->sourceRoot . '/tools/project-generator/package-identity.json';
        try {
            $packageIdentity = is_file($packageIdentityPath)
                ? json_decode((string) file_get_contents($packageIdentityPath), true, 512, JSON_THROW_ON_ERROR)
                : null;
        } catch (Throwable) {
            $packageIdentity = null;
        }
        $anchor = is_array($baseline) ? ($baseline['content_anchor'] ?? null) : null;
        $commit = is_array($anchor) ? ($anchor['commit'] ?? null) : null;
        $tree = is_array($anchor) ? ($anchor['tree'] ?? null) : null;
        $packageCommit = is_array($packageIdentity) ? ($packageIdentity['commit'] ?? null) : null;
        $packageTree = is_array($packageIdentity) ? ($packageIdentity['tree'] ?? null) : null;
        $content = is_array($baseline) ? ($baseline['controlled_content'] ?? null) : null;
        $packageKeys = is_array($packageIdentity) ? array_keys($packageIdentity) : [];
        sort($packageKeys, SORT_STRING);
        if (($baseline['schema_version'] ?? null) !== 4
            || !is_string($commit) || preg_match('/^[0-9a-f]{40}$/D', $commit) !== 1
            || !is_string($tree) || preg_match('/^[0-9a-f]{40}$/D', $tree) !== 1
            || ($packageIdentity['schema_version'] ?? null) !== 1
            || $packageKeys !== ['commit', 'schema_version', 'tree']
            || !is_string($packageCommit)
            || !is_string($packageTree)
            || !is_array($content)
            || ($content['algorithm'] ?? null) !== 'sha256-git-blob-manifest-v1'
            || !is_int($content['file_count']) || $content['file_count'] < 1
            || !is_string($content['digest']) || preg_match('/^[0-9a-f]{64}$/D', $content['digest']) !== 1) {
            throw new ProjectGeneratorException('PROJECT_SOURCE_INVALID', 'Generator source baseline is invalid.');
        }

        $expected = ['file_count' => $content['file_count'], 'digest' => $content['digest']];
        $isGitCheckout = file_exists($this->sourceRoot . '/.git');
        if ($isGitCheckout) {
            if ($packageCommit !== '$Format:%H$' || $packageTree !== '$Format:%T$'
                || trim($this->git([
                    'check-attr', 'export-subst', '--', 'tools/project-generator/package-identity.json',
                ])) !== 'tools/project-generator/package-identity.json: export-subst: set') {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', 'Package archive identity export contract drifted.');
            }
            $topLevel = realpath(trim($this->git(['rev-parse', '--show-toplevel'])));
            if (!is_string($topLevel) || rtrim($topLevel, DIRECTORY_SEPARATOR) !== $this->sourceRoot) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_INVALID', 'Generator source is not its Git checkout root.');
            }
            if (trim($this->git(['status', '--porcelain=v1', '--untracked-files=all'])) !== '') {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DIRTY', 'Generator source checkout must be clean.');
            }
            $head = trim($this->git(['rev-parse', 'HEAD^{commit}']));
            $headTree = trim($this->git(['rev-parse', 'HEAD^{tree}']));
            if (preg_match('/^[0-9a-f]{40}$/D', $head) !== 1
                || preg_match('/^[0-9a-f]{40}$/D', $headTree) !== 1) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_INVALID', 'Generator checkout HEAD identity is invalid.');
            }
            $baselineCommit = trim($this->git(['rev-parse', $commit . '^{commit}']));
            $baselineTree = trim($this->git(['rev-parse', $commit . '^{tree}']));
            if (!hash_equals($commit, $baselineCommit) || !hash_equals($tree, $baselineTree)) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', 'Baseline commit and tree do not match.');
            }
            $this->git(['merge-base', '--is-ancestor', $commit, $head]);
            $committed = $this->controlledGitManifest($commit);
            if ($committed !== $expected) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', 'Baseline controlled-content digest does not match its commit.');
            }
        } elseif (preg_match('/^[0-9a-f]{40}$/D', $packageCommit) !== 1
            || preg_match('/^[0-9a-f]{40}$/D', $packageTree) !== 1) {
            throw new ProjectGeneratorException('PROJECT_SOURCE_INVALID', 'Package archive identity was not expanded by git archive.');
        }

        $checkout = $this->controlledFilesystemManifest();
        if ($checkout !== $expected) {
            throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', 'Generator controlled source differs from the fixed baseline.');
        }

        return $isGitCheckout
            ? ['input_commit' => $head, 'input_tree' => $headTree]
            : ['input_commit' => $packageCommit, 'input_tree' => $packageTree];
    }

    /** @return list<string> */
    private static function controlledSourcePaths(): array
    {
        $paths = ['starter'];
        foreach (self::PACKAGE_SNAPSHOTS as [$relative, $manifest]) {
            foreach ([$manifest, 'LICENSE'] as $entry) {
                $paths[] = $relative . '/' . $entry;
            }
            foreach (self::PACKAGE_MODULES[$relative] as $module) {
                foreach (['src', 'database', 'resources'] as $entry) {
                    $paths[] = $relative . '/' . $module . '/' . $entry;
                }
            }
        }

        return $paths;
    }

    /** @return array{file_count: int, digest: string} */
    private function controlledFilesystemManifest(): array
    {
        $entries = [];
        foreach (self::controlledSourcePaths() as $relative) {
            $path = $this->sourceRoot . '/' . $relative;
            if (is_link($path)) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', "Controlled source is a symbolic link: {$relative}.");
            }
            if (is_file($path)) {
                $this->recordFilesystemEntry($entries, $relative, $path);
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                $entryPath = $file->getPathname();
                $entryRelative = substr($entryPath, strlen($this->sourceRoot) + 1);
                if ($file->isLink() || !$file->isFile()) {
                    throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', "Controlled source entry is unsafe: {$entryRelative}.");
                }
                $this->recordFilesystemEntry($entries, $entryRelative, $entryPath);
            }
        }
        ksort($entries, SORT_STRING);

        return $this->manifestIdentity($entries);
    }

    /** @param array<string, string> $entries */
    private function recordFilesystemEntry(array &$entries, string $relative, string $path): void
    {
        $contents = file_get_contents($path);
        $mode = fileperms($path);
        if (!is_string($contents) || !is_int($mode)) {
            throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', "Controlled source file is unreadable: {$relative}.");
        }
        $gitMode = ($mode & 0111) !== 0 ? '100755' : '100644';
        $blob = sha1('blob ' . strlen($contents) . "\0" . $contents);
        $entries[$relative] = $gitMode . ' ' . $blob;
    }

    /** @return array{file_count: int, digest: string} */
    private function controlledGitManifest(string $commit): array
    {
        $output = $this->git([
            'ls-tree', '-r', '-z', '--full-tree', $commit, '--', ...self::controlledSourcePaths(),
        ]);
        $entries = [];
        foreach (explode("\0", $output) as $record) {
            if ($record === '') {
                continue;
            }
            if (preg_match('/^(100644|100755) blob ([0-9a-f]{40})\t(.+)$/D', $record, $matches) !== 1) {
                throw new ProjectGeneratorException('PROJECT_SOURCE_DRIFT', 'Baseline controlled source contains an unsupported Git entry.');
            }
            $entries[$matches[3]] = $matches[1] . ' ' . $matches[2];
        }
        ksort($entries, SORT_STRING);

        return $this->manifestIdentity($entries);
    }

    /**
     * @param array<string, string> $entries
     * @return array{file_count: int, digest: string}
     */
    private function manifestIdentity(array $entries): array
    {
        $lines = '';
        foreach ($entries as $path => $identity) {
            $lines .= $identity . "\t" . $path . "\n";
        }

        return ['file_count' => count($entries), 'digest' => hash('sha256', $lines)];
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $pipes = [];
        $process = proc_open(
            ['git', '-C', $this->sourceRoot, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->sourceRoot,
        );
        if (!is_resource($process)) {
            throw new ProjectGeneratorException('PROJECT_SOURCE_INVALID', 'Could not inspect generator Git source.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || !is_string($stdout)) {
            $detail = is_string($stderr) ? trim($stderr) : '';
            throw new ProjectGeneratorException(
                'PROJECT_SOURCE_DRIFT',
                $detail === '' ? 'Generator Git source validation failed.' : $detail,
            );
        }

        return $stdout;
    }

    /** @return array{string, bool} */
    private function prepareTarget(string $requested): array
    {
        $segments = preg_split('~[\\\\/]~', $requested);
        if (!is_array($segments) || in_array('..', $segments, true)) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Parent traversal is not allowed.');
        }
        $absolute = str_starts_with($requested, DIRECTORY_SEPARATOR)
            ? $requested
            : ((getcwd() ?: $this->sourceRoot) . DIRECTORY_SEPARATOR . $requested);
        $parts = preg_split('~[\\\\/]+~', $absolute, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target path is invalid.');
        }
        if (is_link($absolute)) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target itself must not be a symbolic link.');
        }
        $target = $this->physicalFuturePath($absolute);
        if ($this->contains($this->sourceRoot, $target) || $this->contains($target, $this->sourceRoot)) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target overlaps the generator source tree.');
        }
        if (file_exists($target) || is_link($target)) {
            if (is_link($target) || !is_dir($target)) {
                throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target is not a regular directory.');
            }
            $entries = scandir($target);
            if (!is_array($entries) || array_values(array_diff($entries, ['.', '..'])) !== []) {
                throw new ProjectGeneratorException('PROJECT_TARGET_NOT_EMPTY', 'Target directory must be empty.');
            }

            return [$target, false];
        }
        if (!@mkdir($target, 0755, true) && !is_dir($target)) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target directory could not be created.');
        }
        $physical = realpath($target);
        if (!is_string($physical) || $physical !== $target || $this->contains($this->sourceRoot, $physical)) {
            @rmdir($target);
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Created target did not preserve the validated boundary.');
        }

        return [$target, true];
    }

    private function physicalFuturePath(string $path): string
    {
        $suffix = [];
        $cursor = rtrim($path, DIRECTORY_SEPARATOR);
        while (!file_exists($cursor) && !is_link($cursor)) {
            $base = basename($cursor);
            if ($base === '' || $base === '.' || $base === '..') {
                throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target path cannot be normalized.');
            }
            array_unshift($suffix, $base);
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target ancestor is unavailable.');
            }
            $cursor = $parent;
        }
        $physical = realpath($cursor);
        if (!is_string($physical)) {
            throw new ProjectGeneratorException('PROJECT_TARGET_UNSAFE', 'Target ancestor cannot be resolved.');
        }

        return rtrim($physical, DIRECTORY_SEPARATOR)
            . ($suffix === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $suffix));
    }

    private function contains(string $root, string $candidate): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);

        return $candidate === $root || str_starts_with($candidate, $root . DIRECTORY_SEPARATOR);
    }

    private function copyPackageSnapshots(string $target): void
    {
        foreach (self::PACKAGE_SNAPSHOTS as [$relative, $manifest]) {
            $source = $this->sourceRoot . '/' . $relative;
            $destination = $target . '/' . $relative;
            foreach ([$manifest, 'LICENSE'] as $required) {
                if (!file_exists($source . '/' . $required)) {
                    throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Package snapshot is incomplete: {$relative}.");
                }
            }
            $this->copyFile($source . '/' . $manifest, $destination . '/' . $manifest);
            $this->copyFile($source . '/LICENSE', $destination . '/LICENSE');
            foreach (self::PACKAGE_MODULES[$relative] as $module) {
                $moduleSource = $source . '/' . $module;
                $moduleDestination = $destination . '/' . $module;
                if (!is_dir($moduleSource . '/src')) {
                    throw new ProjectGeneratorException(
                        'PROJECT_TEMPLATE_INVALID',
                        "Package module snapshot is incomplete: {$relative}/{$module}.",
                    );
                }
                $this->copyTree($moduleSource . '/src', $moduleDestination . '/src');
                foreach (['database', 'resources'] as $optional) {
                    if (is_dir($moduleSource . '/' . $optional)) {
                        $this->copyTree(
                            $moduleSource . '/' . $optional,
                            $moduleDestination . '/' . $optional,
                        );
                    }
                }
            }
        }
    }

    private function copyTree(string $source, string $destination): void
    {
        if (!is_dir($source) || is_link($source)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Template directory is missing or unsafe.');
        }
        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not create a generated directory.');
        }
        $entries = scandir($source);
        if (!is_array($entries)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Template directory cannot be scanned.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sourcePath = $source . '/' . $entry;
            $destinationPath = $destination . '/' . $entry;
            if (is_link($sourcePath)) {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Template symbolic links are not supported.');
            }
            if (is_dir($sourcePath)) {
                $this->copyTree($sourcePath, $destinationPath);
            } elseif (is_file($sourcePath)) {
                $this->copyFile($sourcePath, $destinationPath);
            } else {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Template contains an unsupported entry.');
            }
        }
    }

    private function copyFile(string $source, string $destination): void
    {
        if (!is_file($source) || is_link($source)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Template file is missing or unsafe.');
        }
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not create a generated directory.');
        }
        $contents = file_get_contents($source);
        if (!is_string($contents) || file_put_contents($destination, $contents, LOCK_EX) === false) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not copy a template file.');
        }
        $mode = fileperms($source);
        if (is_int($mode)) {
            chmod($destination, $mode & 0777);
        }
    }

    /** @param list<string> $selected */
    private function selectFeatures(string $target, array $selected): void
    {
        foreach (self::FEATURES as $key => $feature) {
            if (in_array($key, $selected, true)) {
                continue;
            }
            foreach (['backend_root', 'frontend_host', 'backend_test', 'frontend_test'] as $pathKey) {
                $this->removePath($target . '/' . $feature[$pathKey]);
            }
            if ($key === 'file-media') {
                $this->removePath($target . '/backend/config/file-media.php');
            }
            if ($key === 'notification-sms') {
                $this->removePath($target . '/backend/config/notification-sms.php');
            }
            if ($key === 'integration-security') {
                $this->removePath($target . '/backend/config/integration-security.php');
            }
        }
    }

    private function replaceNamespaces(string $target, string $namespace): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        );
        $doubleNamespace = str_replace('\\', '\\\\', $namespace);
        $moduleNamespace = $namespace . '\\Modules';
        $doubleModuleNamespace = str_replace('\\', '\\\\', $moduleNamespace);
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            if (!in_array($file->getExtension(), ['php', 'json', 'md'], true)) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents)) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not read a generated source file.');
            }
            $contents = str_replace('PeanutAdmin\\\\InternalStarter', $doubleNamespace, $contents);
            $contents = str_replace('ExampleHost\\\\App\\\\Modules', $doubleModuleNamespace, $contents);
            $contents = str_replace('PeanutAdmin\\InternalStarter', $namespace, $contents);
            $contents = str_replace('ExampleHost\\App\\Modules', $moduleNamespace, $contents);
            if (file_put_contents($file->getPathname(), $contents, LOCK_EX) === false) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not write a generated source file.');
            }
        }
    }

    /** @param array{input_commit: string, input_tree: string} $sourceIdentity */
    private function writeProjectFiles(string $target, GenerationRequest $request, array $sourceIdentity): void
    {
        $this->writeJson($target . '/peanut-project.json', $this->metadata($request, $sourceIdentity));
        $this->writePhpConfig($target . '/backend/config/auth.php', [
            'admin_client_key' => $request->adminClientKey,
            'tenant_clients' => $request->tenantClients,
        ]);
        $moduleRoots = ['backend/src/Modules/Example/Greeting'];
        $frontendComponents = ['example.greeting.page', 'peanut.ops-console.page'];
        foreach ($request->features as $feature) {
            $moduleRoots[] = self::FEATURES[$feature]['backend_root'];
            $frontendComponents[] = self::FEATURES[$feature]['frontend_component'];
        }
        $registeredClients = array_values(array_unique([
            ...array_column($request->tenantClients, 'key'),
            'platform-web',
        ]));
        $this->writePhpConfig($target . '/backend/config/modules.php', [
            'kernel_version' => '1.0.0',
            'roots' => $moduleRoots,
            'frontend_components' => $frontendComponents,
            'registered_client_keys' => $registeredClients,
        ]);
        $this->adaptTenantClientRuntimeFactory($target);
        $this->adaptModuleMenus($target, $request->features, $request->adminClientKey);
        $this->writeClients($target . '/frontend/src/clients.ts', $request->tenantClients);
        $this->writeClientVerification($target . '/frontend/verification/clients.spec.ts', $request->tenantClients);
        $this->writeFrontendModules($target . '/frontend/src/app/modules.ts', $request->features);
        $this->writeFrontendApp($target . '/frontend/src/App.vue', $request);
        $this->writeFrontendIndex($target . '/frontend/index.html', $request->displayName);
        $this->writeEnvironment($target . '/.env.example', $request);
        $this->writeReadme($target . '/README.md', $request);
        $this->writeBackendSmoke($target . '/backend/tests/smoke.php', $request->features, $request->phpNamespace);
        $this->adaptAuthFixture($target, $request->tenantClients, $request->adminClientKey);
        $this->adaptFeatureFixtures($target, $request->features);
        $this->adaptPackageManifests($target, $request);
    }

    /**
     * @param array{input_commit: string, input_tree: string} $sourceIdentity
     * @return array<string, mixed>
     */
    private function metadata(GenerationRequest $request, array $sourceIdentity): array
    {
        return [
            'schema_version' => 1,
            'generator' => ['name' => 'peanut-admin/create-project', 'schema_version' => 1],
            'peanut_admin' => [
                'input_commit' => $sourceIdentity['input_commit'],
                'input_tree' => $sourceIdentity['input_tree'],
            ],
            'project' => [
                'slug' => $request->slug,
                'display_name' => $request->displayName,
                'php_namespace' => $request->phpNamespace,
                'brand' => $request->brand,
                'profile' => $request->profile,
                'tenant_clients' => $request->tenantClients,
                'admin_client_key' => $request->adminClientKey,
                'features' => $request->features,
            ],
            'secrets' => [
                'embedded' => false,
                'policy' => 'supply-outside-generated-source',
            ],
        ];
    }

    /** @param array<string, mixed> $values */
    private function writePhpConfig(string $path, array $values): void
    {
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($values, true) . ";\n";
        $contents = preg_replace('/^ {2}/m', '    ', $contents) ?? $contents;
        $this->write($path, $contents);
    }

    private function adaptTenantClientRuntimeFactory(string $target): void
    {
        $path = $target . '/backend/src/Auth/TenantAuthRuntimeFactory.php';
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Tenant Client Host factory is missing.');
        }
        $legacy = <<<'PHP'
        $config = require $root . '/backend/config/auth.php';
        $clientKeys = $config['tenant_clients'] ?? null;
        if (!is_array($clientKeys) || !array_is_list($clientKeys)) {
            throw new RuntimeException('Starter Tenant Client configuration is invalid.');
        }
        $this->clients = new TenantClientRegistry(array_map('strval', $clientKeys));
PHP;
        $structured = <<<'PHP'
        $config = require $root . '/backend/config/auth.php';
        $definitions = $config['tenant_clients'] ?? null;
        $adminClientKey = $config['admin_client_key'] ?? null;
        if (!is_array($definitions) || !array_is_list($definitions) || !is_string($adminClientKey)) {
            throw new RuntimeException('Starter Tenant Client configuration is invalid.');
        }
        $clientKeys = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)
                || !is_string($definition['key'] ?? null)
                || !is_string($definition['api_prefix'] ?? null)) {
                throw new RuntimeException('Starter Tenant Client configuration is invalid.');
            }
            $clientKeys[] = $definition['key'];
        }
        if (!in_array($adminClientKey, $clientKeys, true)) {
            throw new RuntimeException('Starter admin Client is not registered.');
        }
        $this->clients = new TenantClientRegistry($clientKeys);
PHP;
        if (substr_count($contents, $legacy) !== 1) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Tenant Client Host factory contract drifted.');
        }
        $this->write($path, str_replace($legacy, $structured, $contents));
    }

    /** @param list<string> $features */
    private function adaptModuleMenus(string $target, array $features, string $adminClientKey): void
    {
        foreach ($features as $feature) {
            $path = $target . '/' . self::FEATURES[$feature]['backend_root'] . '/Resources/menus.json';
            try {
                $menus = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $menus = null;
            }
            if (!is_array($menus) || !array_is_list($menus) || $menus === []) {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Module menu contract is invalid: {$feature}.");
            }
            foreach ($menus as &$menu) {
                if (!is_array($menu) || !isset($menu['client_keys']) || !is_array($menu['client_keys'])) {
                    throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Module menu Client contract is invalid: {$feature}.");
                }
                $menu['client_keys'] = [$adminClientKey];
            }
            unset($menu);
            $this->writeJson($path, $menus);
        }
    }

    /** @param list<array{key: string, api_prefix: string}> $clients */
    private function writeClients(string $path, array $clients): void
    {
        $rows = array_map(
            static fn(array $client): string => sprintf(
                "  { key: '%s', apiPrefix: '%s' },",
                $client['key'],
                $client['api_prefix'],
            ),
            $clients,
        );
        $contents = <<<'TS'
import { createProtectedFetch } from '@peanut-admin/admin/core'
import type { RefreshCoordinator } from '@peanut-admin/admin/core'

export interface TenantClientDefinition {
  key: string
  apiPrefix: string
}

export const tenantClients = [
__CLIENTS__
] as const satisfies readonly TenantClientDefinition[]

export interface TenantClientTransportOptions {
  baseUrl: string
  fetch?: (request: Request) => Promise<Response>
  getAccessToken: () => string | null
  setAccessToken: (token: string) => void
  refresh: () => Promise<string | null>
  refreshCoordinator?: RefreshCoordinator
}

export const createTenantClientTransport = (
  definition: TenantClientDefinition,
  options: TenantClientTransportOptions,
): ((request: Request) => Promise<Response>) => createProtectedFetch({
  ...options,
  refreshScope: `${definition.key}:tenant`,
  isAllowedPath: pathname => pathname.startsWith(definition.apiPrefix),
  isCredentialExchange: pathname => /\/auth\/(?:login|refresh|tenants\/select)$/.test(pathname),
})
TS;
        $this->write($path, str_replace('__CLIENTS__', implode("\n", $rows), $contents) . "\n");
    }

    /** @param list<string> $features */
    private function writeFrontendModules(string $path, array $features): void
    {
        $imports = ["import { exampleGreetingModule } from '../modules/example-greeting'"];
        $types = [];
        $setup = [];
        $modules = ['exampleGreetingModule'];
        $returns = [];
        $map = [
            'settings' => ['PeanutSettingsHostOptions', 'createPeanutSettingsHost', 'settings'],
            'reference-codes' => ['PeanutReferenceCodesHostOptions', 'createPeanutReferenceCodesHost', 'referenceCodes'],
            'file-media' => ['PeanutFileMediaHostOptions', 'createPeanutFileMediaHost', 'fileMedia'],
            'task-job' => ['PeanutTaskJobHostOptions', 'createPeanutTaskJobHost', 'taskJob'],
            'notification-sms' => ['PeanutNotificationSmsHostOptions', 'createPeanutNotificationSmsHost', 'notificationSms'],
            'import-export' => ['PeanutImportExportHostOptions', 'createPeanutImportExportHost', 'importExport'],
            'integration-security' => ['PeanutIntegrationSecurityHostOptions', 'createPeanutIntegrationSecurityHost', 'integrationSecurity'],
        ];
        foreach ($features as $feature) {
            [$type, $factory, $variable] = $map[$feature];
            $host = 'peanut-' . $feature;
            $imports[] = "import { {$factory} } from '../modules/{$host}'";
            $imports[] = "import type { {$type} } from '../modules/{$host}'";
            $types[] = $type;
            $setup[] = "  const {$variable} = {$factory}(options)";
            $modules[] = "{$variable}.module";
            $returns[] = "    {$variable}Module: {$variable}.module,";
            $returns[] = "    {$variable}Runtime: {$variable}.runtime,";
        }
        $imports[] = "import { createPeanutOpsConsoleHost } from '../modules/peanut-ops-console'";
        $imports[] = "import type { PeanutOpsConsoleHostOptions } from '../modules/peanut-ops-console'";
        $types[] = 'PeanutOpsConsoleHostOptions';
        $setup[] = '  const opsConsole = createPeanutOpsConsoleHost(options)';
        $modules[] = 'opsConsole.module';
        $returns[] = '    opsConsoleModule: opsConsole.module,';
        $returns[] = '    opsConsoleRuntime: opsConsole.runtime,';
        $typeExpression = $types === [] ? 'Record<string, never>' : implode(' & ', $types);
        $contents = implode("\n", $imports)
            . ($imports === [] ? '' : "\n\n")
            . "export type StarterModuleOptions = {$typeExpression}\n\n"
            . "export const createStarterModules = (options: StarterModuleOptions) => {\n"
            . ($setup === [] ? "  void options\n" : implode("\n", $setup) . "\n")
            . "\n  return {\n"
            . '    modules: [' . implode(', ', $modules) . "] as const,\n"
            . ($returns === [] ? '' : implode("\n", $returns) . "\n")
            . "  }\n}\n";
        $this->write($path, $contents);
    }

    private function writeFrontendApp(string $path, GenerationRequest $request): void
    {
        $jsonFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $brand = json_encode($request->brand, $jsonFlags);
        $name = json_encode($request->displayName, $jsonFlags);
        $slug = json_encode($request->slug, $jsonFlags);
        $contents = <<<VUE
<script setup lang="ts">
import { ADMIN_CORE_PACKAGE } from '@peanut-admin/admin/core'
import { AdminShell, PageContent, PageHeader, ShellHeader, ShellSidebar } from '@peanut-admin/admin/shell'

const projectBrand = {$brand}
const projectDisplayName = {$name}
const projectSlug = {$slug}
</script>

<template>
  <AdminShell class="starter-shell">
    <template #header>
      <ShellHeader><span v-text="projectBrand" /></ShellHeader>
    </template>
    <template #sidebar>
      <ShellSidebar><span v-text="projectDisplayName" /></ShellSidebar>
    </template>
    <PageHeader><span v-text="projectDisplayName" /></PageHeader>
    <PageContent>
      <p data-testid="project-slug" v-text="projectSlug" />
      <p data-testid="package-name">{{ ADMIN_CORE_PACKAGE }}</p>
    </PageContent>
  </AdminShell>
</template>
VUE;
        $this->write($path, $contents . "\n");
    }

    private function writeFrontendIndex(string $path, string $displayName): void
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || preg_match('/<title>.*?<\/title>/s', $contents) !== 1) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Frontend title template is missing.');
        }
        $title = htmlspecialchars($displayName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->write($path, preg_replace('/<title>.*?<\/title>/s', "<title>{$title}</title>", $contents) ?? $contents);
    }

    private function writeEnvironment(string $path, GenerationRequest $request): void
    {
        $contents = "APP_ENV=development\n"
            . "APP_DEBUG=false\n"
            . "APP_SLUG={$request->slug}\n"
            . "VITE_API_BASE_URL=/api\n"
            . "APP_KEY=\n"
            . "PEANUT_IDENTIFIER_HMAC_KEY=\n"
            . "PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID=\n"
            . "PEANUT_SETTINGS_SECRET_KEYS=\n";
        $this->write($path, $contents);
    }

    private function writeReadme(string $path, GenerationRequest $request): void
    {
        $features = $request->features === [] ? 'none' : implode(', ', $request->features);
        $clients = implode(', ', array_column($request->tenantClients, 'key'));
        $authCheck = count($request->tenantClients) >= 2 ? "php backend/tests/auth-clients.php\n" : '';
        $displayName = self::markdownText($request->displayName);
        $phpNamespace = self::markdownText($request->phpNamespace);
        $brand = self::markdownText($request->brand);
        $contents = <<<MD
# {$displayName}

Generated from Peanut Admin's `standard-admin` profile.

- Project slug: `{$request->slug}`
- PHP namespace: `{$phpNamespace}`
- Brand: `{$brand}`
- Tenant Clients: {$clients}
- Enabled first-party Modules: {$features}
- Removable fictional Module: `example.greeting`

`peanut-project.json` records the exact Peanut Admin input commit and tree. The
generator never writes credentials. Fill the blank values in `.env.example`
through the deployment's secret system before running the application.

## Local verification

After installing from approved package sources or the included snapshots:

```bash
composer install --working-dir backend
pnpm install --frozen-lockfile
php backend/tests/smoke.php
{$authCheck}pnpm typecheck
pnpm test
pnpm build
```

Application-specific Modules belong under this project's namespace. Regenerating
into this directory is intentionally rejected; the generator never overwrites an
existing project.
MD;
        $this->write($path, $contents . "\n");
    }

    private static function markdownText(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '`' => '\\`',
        ]);
    }

    /** @param list<string> $features */
    private function writeBackendSmoke(string $path, array $features, string $namespace): void
    {
        $keys = self::orderedModuleKeys($features);
        $tables = [];
        $owned = [
            'settings' => [
                'pa_setting_definition', 'pa_setting_deployment_value',
                'pa_setting_tenant_value', 'pa_setting_target_value',
            ],
            'reference-codes' => [
                'pa_reference_code_set', 'pa_reference_code_entry', 'pa_reference_code_entry_version',
            ],
            'file-media' => [
                'pa_file_object', 'pa_file_delivery_policy', 'pa_file_image_metadata',
                'pa_file_image_variant', 'pa_file_delivery_nonce',
            ],
            'task-job' => ['pa_task_job', 'pa_task_job_attempt', 'pa_task_job_event'],
            'notification-sms' => [
                'pa_notification_template', 'pa_notification_message', 'pa_notification_attachment',
                'pa_notification_outbox', 'pa_sms_rate_bucket', 'pa_notification_event',
            ],
            'import-export' => ['pa_import_export_operation', 'pa_import_export_row_error'],
            'integration-security' => [
                'pa_integration_machine_identity', 'pa_integration_webhook_endpoint',
                'pa_integration_webhook_delivery', 'pa_integration_webhook_attempt',
                'pa_integration_security_event',
            ],
        ];
        foreach ($features as $feature) {
            foreach ($owned[$feature] as $table) {
                $tables[$table] = 'peanut.' . $feature;
            }
        }
        ksort($tables);
        $keysExport = var_export($keys, true);
        $tablesExport = var_export($tables, true);
        $contents = <<<PHP
<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use {$namespace}\Module\ModuleRegistryFactory;
use PeanutAdmin\Kernel\Package as KernelPackage;

require dirname(__DIR__) . '/vendor/autoload.php';

\$root = dirname(__DIR__, 2);
\$registry = (new ModuleRegistryFactory(\$root))->compile();
\$ownedTableOwners = \$registry->ownedTableOwners;
ksort(\$ownedTableOwners);
\$kernelRoot = InstalledVersions::getInstallPath(KernelPackage::NAME);
\$dataPermissionRoot = InstalledVersions::getInstallPath(DataPermissionPackage::NAME);
\$kernelRoot = is_string(\$kernelRoot) ? \$kernelRoot . '/kernel' : \$kernelRoot;
\$dataPermissionRoot = is_string(\$dataPermissionRoot) ? \$dataPermissionRoot . '/data-permission' : \$dataPermissionRoot;
\$valid = KernelPackage::VERSION === '0.1.0'
    && DataPermissionPackage::VERSION === '0.1.0'
    && \$registry->moduleKeys() === {$keysExport}
    && \$ownedTableOwners === {$tablesExport}
    && is_string(\$kernelRoot)
    && is_dir(\$kernelRoot . '/database/migrations')
    && is_file(\$kernelRoot . '/resources/schemas/module-manifest.schema.json')
    && is_string(\$dataPermissionRoot)
    && is_dir(\$dataPermissionRoot . '/database/migrations');

if (!\$valid) {
    fwrite(STDERR, "ERROR: generated project package smoke failed\n");
    exit(1);
}

fwrite(STDOUT, "Generated project backend test: OK\n");
PHP;
        $this->write($path, $contents . "\n");
    }

    /** @param list<array{key: string, api_prefix: string}> $clients */
    private function adaptAuthFixture(string $target, array $clients, string $adminClientKey): void
    {
        $path = $target . '/backend/tests/auth-clients.php';
        if (count($clients) < 2) {
            $this->removePath($path);

            return;
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Tenant Client fixture is missing.');
        }
        $secondaryClientKey = null;
        foreach ($clients as $client) {
            if ($client['key'] !== $adminClientKey) {
                $secondaryClientKey = $client['key'];
                break;
            }
        }
        if (!is_string($secondaryClientKey)) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Tenant Client fixture needs two distinct Clients.');
        }
        $legacyExpected = "['operations-web', 'reporting-web']";
        if (substr_count($contents, $legacyExpected) !== 1
            || substr_count($contents, "'operations-web'") < 2
            || substr_count($contents, "'reporting-web'") < 2) {
            throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', 'Tenant Client fixture contract drifted.');
        }
        $contents = str_replace($legacyExpected, '__GENERATED_SESSION_CLIENT_KEYS__', $contents);
        $contents = strtr($contents, [
            "'operations-web'" => var_export($adminClientKey, true),
            "'reporting-web'" => var_export($secondaryClientKey, true),
        ]);
        $sessionClientKeys = [$adminClientKey, $secondaryClientKey];
        sort($sessionClientKeys, SORT_STRING);
        $contents = str_replace(
            '__GENERATED_SESSION_CLIENT_KEYS__',
            var_export($sessionClientKeys, true),
            $contents,
        );
        $this->write($path, $contents);
    }

    /** @param list<array{key: string, api_prefix: string}> $clients */
    private function writeClientVerification(string $path, array $clients): void
    {
        $keys = json_encode(array_column($clients, 'key'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $prefixes = json_encode(array_column($clients, 'api_prefix'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $allowedPath = $clients[0]['api_prefix'] . 'items';
        $rejectedPath = count($clients) > 1 ? $clients[1]['api_prefix'] . 'items' : '/api/unregistered/v1/items';
        $contents = <<<TS
import { createMemoryRefreshCoordinator } from '@peanut-admin/admin/core'
import { describe, expect, it, vi } from 'vitest'

import { createTenantClientTransport, tenantClients } from '../src/clients'

describe('generated project Tenant Clients', () => {
  it('defines the requested Client keys and API prefixes', () => {
    expect(tenantClients.map(client => client.key)).toEqual({$keys})
    expect(tenantClients.map(client => client.apiPrefix)).toEqual({$prefixes})
  })

  it('keeps a protected transport inside its Client API prefix and origin', async () => {
    let token = 'fixture-access-token'
    const fetcher = vi.fn(async () => new Response(JSON.stringify({ data: [] }), { status: 200 }))
    const getAccessToken = vi.fn(() => token)
    const refresh = vi.fn(async () => 'fixture-rotated-token')
    const transport = createTenantClientTransport(tenantClients[0], {
      baseUrl: 'https://example.test',
      fetch: fetcher,
      getAccessToken,
      setAccessToken: value => { token = value },
      refresh,
      refreshCoordinator: createMemoryRefreshCoordinator(),
    })

    await expect(transport(new Request('https://example.test{$allowedPath}'))).resolves.toMatchObject({ ok: true })
    await expect(
      transport(new Request('https://example.test{$rejectedPath}')),
    ).rejects.toThrow('API_AUDIENCE_MISMATCH')
    await expect(
      transport(new Request('https://other.test{$allowedPath}')),
    ).rejects.toThrow('API_ORIGIN_MISMATCH')
  })
})
TS;
        $this->write($path, $contents . "\n");
    }

    /** @param list<string> $features */
    private function adaptFeatureFixtures(string $target, array $features): void
    {
        $keys = ['example.greeting', ...array_map(static fn(string $feature): string => 'peanut.' . $feature, $features), 'peanut.ops-console'];
        $phpExpected = var_export(self::orderedModuleKeys($features), true);
        $canonicalKeys = self::orderedModuleKeys(array_keys(self::FEATURES));
        $phpPattern = '~\\[\\s*' . implode(
            ',\\s*',
            array_map(static fn(string $key): string => "'" . preg_quote($key, '~') . "'", $canonicalKeys),
        ) . ',?\\s*\\]~';
        foreach (['backend/tests/settings.php', 'backend/tests/reference-codes.php'] as $relative) {
            $path = $target . '/' . $relative;
            if (!is_file($path)) {
                continue;
            }
            $contents = (string) file_get_contents($path);
            $updated = preg_replace(
                $phpPattern,
                $phpExpected,
                $contents,
                1,
                $count,
            );
            if (!is_string($updated) || $count !== 1) {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Feature fixture contract drifted: {$relative}.");
            }
            $this->write($path, $updated);
        }
        $tsExpected = json_encode($keys, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        foreach ([
            'frontend/tests/settings.spec.ts',
            'frontend/tests/reference-codes.spec.ts',
            'frontend/tests/file-media.spec.ts',
            'frontend/tests/task-job.spec.ts',
            'frontend/tests/notification-sms.spec.ts',
            'frontend/tests/import-export.spec.ts',
            'frontend/tests/integration-security.spec.ts',
        ] as $relative) {
            $path = $target . '/' . $relative;
            if (!is_file($path)) {
                continue;
            }
            $contents = (string) file_get_contents($path);
            $updated = preg_replace(
                '~expect\(host\.modules\.map\(module => module\.key\)\)\.toEqual\(\[[\s\S]*?\]\)~',
                "expect(host.modules.map(module => module.key)).toEqual({$tsExpected})",
                $contents,
                1,
                $count,
            );
            if (!is_string($updated) || $count !== 1) {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Web feature fixture contract drifted: {$relative}.");
            }
            $this->write($path, $updated);
        }
    }

    /** @param list<string> $features @return list<string> */
    private static function orderedModuleKeys(array $features): array
    {
        $selected = array_fill_keys($features, true);
        $pending = $features;
        sort($pending, SORT_STRING);
        $visiting = [];
        $visited = [];
        $keys = ['example.greeting'];
        $visit = function (string $feature) use (&$visit, &$visiting, &$visited, &$keys, $selected): void {
            if (isset($visited[$feature])) {
                return;
            }
            if (isset($visiting[$feature])) {
                throw new ProjectGeneratorException('PROJECT_FEATURE_DEPENDENCY_INVALID', 'Feature dependencies contain a cycle.');
            }
            $visiting[$feature] = true;
            foreach (self::FEATURE_DEPENDENCIES[$feature] ?? [] as $dependency) {
                if (isset($selected[$dependency])) {
                    $visit($dependency);
                }
            }
            unset($visiting[$feature]);
            $visited[$feature] = true;
            $keys[] = 'peanut.' . $feature;
        };
        foreach ($pending as $feature) {
            $visit($feature);
        }

        return $keys;
    }

    private function adaptPackageManifests(string $target, GenerationRequest $request): void
    {
        $composerPath = $target . '/backend/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $composer['name'] = $request->slug . '/backend';
        $composer['description'] = $request->displayName . ' ThinkPHP host';
        $composer['autoload']['psr-4'] = [$request->phpNamespace . '\\' => 'src/'];
        $this->writeJson($composerPath, $composer);
        $lockPath = $target . '/backend/composer.lock';
        $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
        $lock['content-hash'] = self::composerContentHash($composer);
        $this->writeJson($lockPath, $lock);

        $frontendPath = $target . '/frontend/package.json';
        $frontend = json_decode((string) file_get_contents($frontendPath), true, 512, JSON_THROW_ON_ERROR);
        $frontend['name'] = '@' . $request->slug . '/admin';
        $this->writeJson($frontendPath, $frontend);

        $rootPath = $target . '/package.json';
        $package = json_decode((string) file_get_contents($rootPath), true, 512, JSON_THROW_ON_ERROR);
        $package['name'] = $request->slug;
        foreach (['build', 'test', 'typecheck'] as $script) {
            $package['scripts'][$script] = "pnpm --filter @{$request->slug}/admin {$script}";
        }
        $this->writeJson($rootPath, $package);
    }

    /** @param array<string, mixed> $composer */
    private static function composerContentHash(array $composer): string
    {
        $relevant = [];
        foreach ([
            'name', 'version', 'require', 'require-dev', 'conflict', 'replace',
            'provide', 'minimum-stability', 'prefer-stable', 'repositories', 'extra',
        ] as $key) {
            if (array_key_exists($key, $composer)) {
                $relevant[$key] = $composer[$key];
            }
        }
        if (isset($composer['config']['platform']) && is_array($composer['config']['platform'])) {
            $platform = $composer['config']['platform'];
            ksort($platform);
            $relevant['config']['platform'] = $platform;
        }
        ksort($relevant);
        $encoded = json_encode($relevant, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not hash the Composer contract.');
        }

        return md5($encoded);
    }

    private function assertGeneratedBoundary(string $target): void
    {
        foreach (['peanut-project.json', 'backend/composer.lock', 'pnpm-lock.yaml'] as $relative) {
            if (!is_file($target . '/' . $relative)) {
                throw new ProjectGeneratorException('PROJECT_TEMPLATE_INVALID', "Required generated file is missing: {$relative}.");
            }
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Generated output contains a symbolic link.');
            }
            if (!$file->isFile()) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (is_string($contents) && str_contains($contents, $this->sourceRoot)) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Generated output contains its source path.');
            }
        }
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->write($path, $encoded . "\n");
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not write a generated project file.');
        }
    }

    private function cleanupOwnedTarget(string $target, string $marker, string $owner, bool $targetCreated): void
    {
        $contents = is_file($marker) ? file_get_contents($marker) : false;
        if (!is_string($contents) || !hash_equals($owner . "\n", $contents)) {
            return;
        }
        $entries = scandir($target);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removePath($target . '/' . $entry);
            }
        }
        if ($targetCreated) {
            @rmdir($target);
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!@unlink($path)) {
                throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not remove generated content.');
            }

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        if (!is_array($entries)) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not scan generated content.');
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removePath($path . '/' . $entry);
            }
        }
        if (!@rmdir($path)) {
            throw new ProjectGeneratorException('PROJECT_GENERATION_FAILED', 'Could not remove a generated directory.');
        }
    }
}

final class ProjectGeneratorCli
{
    /** @param list<string> $arguments */
    public static function run(string $root, array $arguments): int
    {
        try {
            $request = self::request($arguments);
            $metadata = (new ProjectGenerator($root))->generate($request);
            fwrite(STDOUT, json_encode([
                'status' => 'created',
                'project' => $metadata['project']['slug'],
                'input_commit' => $metadata['peanut_admin']['input_commit'],
                'input_tree' => $metadata['peanut_admin']['input_tree'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

            return 0;
        } catch (ProjectGeneratorException $exception) {
            fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");

            return 64;
        } catch (Throwable) {
            fwrite(STDERR, "ERROR: PROJECT_GENERATION_FAILED: Generation failed without publishing partial output.\n");

            return 1;
        }
    }

    /** @param list<string> $arguments */
    private static function request(array $arguments): GenerationRequest
    {
        $single = [];
        $clients = [];
        $features = [];
        for ($index = 0; $index < count($arguments); $index++) {
            $option = $arguments[$index];
            if (!in_array($option, [
                '--target', '--slug', '--display-name', '--php-namespace', '--brand',
                '--profile', '--tenant-client', '--admin-client', '--feature',
            ], true)) {
                throw new ProjectGeneratorException('PROJECT_OPTION_UNKNOWN', "Unknown option: {$option}.");
            }
            $value = $arguments[++$index] ?? null;
            if (!is_string($value) || $value === '') {
                throw new ProjectGeneratorException('PROJECT_OPTION_MISSING', "Missing value for {$option}.");
            }
            if ($option === '--tenant-client') {
                $separator = strpos($value, '=');
                if ($separator === false) {
                    throw new ProjectGeneratorException('PROJECT_TENANT_CLIENT_INVALID', 'Use key=/api/path/v1/.');
                }
                $clients[] = [
                    'key' => substr($value, 0, $separator),
                    'api_prefix' => substr($value, $separator + 1),
                ];
            } elseif ($option === '--feature') {
                $features[] = $value;
            } else {
                if (isset($single[$option])) {
                    throw new ProjectGeneratorException('PROJECT_OPTION_DUPLICATE', "Duplicate option: {$option}.");
                }
                $single[$option] = $value;
            }
        }
        foreach (['--target', '--slug', '--display-name', '--php-namespace', '--brand'] as $required) {
            if (!isset($single[$required])) {
                throw new ProjectGeneratorException('PROJECT_OPTION_MISSING', "Required option is missing: {$required}.");
            }
        }
        $canonicalFeatures = [];
        foreach (['settings', 'reference-codes', 'file-media', 'task-job', 'notification-sms', 'import-export', 'integration-security'] as $feature) {
            if (in_array($feature, $features, true)) {
                $canonicalFeatures[] = $feature;
            }
        }
        foreach ($features as $feature) {
            if (!in_array($feature, ['settings', 'reference-codes', 'file-media', 'task-job', 'notification-sms', 'import-export', 'integration-security'], true)) {
                $canonicalFeatures[] = $feature;
            }
        }
        if (count(array_unique($features)) !== count($features)) {
            throw new ProjectGeneratorException('PROJECT_FEATURE_UNKNOWN', 'A first-party feature was selected more than once.');
        }
        $adminClientKey = $single['--admin-client'] ?? null;
        if (!is_string($adminClientKey)) {
            if (count($clients) !== 1) {
                throw new ProjectGeneratorException(
                    'PROJECT_ADMIN_CLIENT_MISSING',
                    'Use --admin-client when more than one Tenant Client is declared.',
                );
            }
            $adminClientKey = $clients[0]['key'];
        }

        return new GenerationRequest(
            $single['--target'],
            $single['--slug'],
            $single['--display-name'],
            $single['--php-namespace'],
            $single['--brand'],
            $single['--profile'] ?? 'standard-admin',
            $clients,
            $adminClientKey,
            $canonicalFeatures,
        );
    }
}
