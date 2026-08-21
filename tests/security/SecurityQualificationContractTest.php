<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityQualificationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testEveryG07ControlHasExecutableEvidence(): void
    {
        $path = $this->root . '/tests/security/g07-evidence.json';
        self::assertFileExists($path);
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $document['schema_version'] ?? null);
        $runners = $this->runners($document['runners'] ?? null);

        $expected = [
            ...$this->ids('TEN', 20),
            ...$this->ids('AUTH', 23),
            ...$this->ids('PERM', 39),
            ...$this->ids('SYS', 22),
            ...$this->ids('WEB', 12),
        ];
        $groups = $document['groups'] ?? [];
        self::assertIsArray($groups);
        $actual = [];
        foreach ($groups as $group) {
            self::assertIsArray($group);
            $ids = $group['ids'] ?? null;
            $evidence = $group['evidence'] ?? null;
            self::assertIsArray($ids);
            self::assertIsArray($evidence);
            self::assertNotSame([], $ids);
            self::assertNotSame([], $evidence);
            foreach ($evidence as $reference) {
                self::assertIsString($reference);
                [$relativePath, $symbol] = array_pad(explode('::', $reference, 2), 2, '');
                self::assertNotSame('', $symbol);
                $evidencePath = $this->root . '/' . $relativePath;
                self::assertFileExists($evidencePath, $relativePath);
                self::assertStringContainsString($symbol, (string) file_get_contents($evidencePath), $reference);
                self::assertNotSame(
                    [],
                    array_keys(array_filter(
                        $runners,
                        static fn(array $runner): bool => self::pathBelongsToRunner($relativePath, $runner),
                    )),
                    "G-07 evidence is not bound to an executable runner: {$reference}",
                );
            }
            foreach ($ids as $id) {
                self::assertIsString($id);
                self::assertArrayNotHasKey($id, $actual, "Duplicate G-07 control {$id}");
                $actual[$id] = true;
            }
        }

        sort($expected);
        $actualIds = array_keys($actual);
        sort($actualIds);
        self::assertSame($expected, $actualIds);
    }

    public function testEveryEvidenceRunnerIsInvokedByTheRepositoryGate(): void
    {
        $document = json_decode(
            (string) file_get_contents($this->root . '/tests/security/g07-evidence.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $runners = $this->runners($document['runners'] ?? null);
        $repositoryGate = (string) file_get_contents($this->root . '/scripts/check');

        foreach ($runners as $id => $runner) {
            self::assertStringContainsString($runner['command'], $repositoryGate, $id);
            $verificationSource = '';
            foreach ($runner['verification_files'] as $relativePath) {
                $path = $this->root . '/' . $relativePath;
                self::assertFileExists($path, $relativePath);
                $verificationSource .= (string) file_get_contents($path);
            }
            foreach ($runner['markers'] as $marker) {
                self::assertStringContainsString($marker, $verificationSource, "{$id}: {$marker}");
            }
        }
    }

    public function testFullStackEvidenceCannotUseApiInterceptionOrSkipMarkers(): void
    {
        $fullStack = (string) file_get_contents(
            $this->root . '/frontend/tests/e2e/full-stack.e2e.ts',
        );
        self::assertStringContainsString(
            'real tenant login reaches multi-target read and single-target write',
            $fullStack,
        );
        self::assertStringContainsString(
            'real platform login reaches the protected tenant collection',
            $fullStack,
        );
        self::assertDoesNotMatchRegularExpression(
            '/(?:page|context)\.route|routeFromHAR|installApiFixture/',
            $fullStack,
        );

        $browserTests = '';
        foreach (glob($this->root . '/frontend/tests/e2e/*.e2e.ts') ?: [] as $path) {
            $browserTests .= (string) file_get_contents($path);
        }
        self::assertDoesNotMatchRegularExpression('/test\.(?:skip|fixme)\s*\(/', $browserTests);
        $browserRunner = (string) file_get_contents($this->root . '/scripts/test-browser');
        self::assertStringContainsString(
            'full-stack browser evidence must not intercept API requests',
            $browserRunner,
        );
        self::assertStringContainsString('export PEANUT_BROWSER_BACKEND_PORT=', $browserRunner);
        self::assertStringContainsString('export PEANUT_BROWSER_FRONTEND_PORT=', $browserRunner);
    }

    public function testSecurityGateFailsWhenAnyTestIsSkipped(): void
    {
        $script = $this->root . '/scripts/test-security';
        self::assertFileExists($script);
        self::assertTrue(is_executable($script), $script);
        $contents = (string) file_get_contents($script);
        self::assertStringContainsString('--log-junit', $contents);
        self::assertStringContainsString('skipped', $contents);
        self::assertFileExists($this->root . '/docs/security/asvs-p0-map.md');
    }

    /**
     * @return array<string, array{
     *   command: string,
     *   evidence_roots: list<string>,
     *   verification_files: list<string>,
     *   markers: list<string>
     * }>
     */
    private function runners(mixed $value): array
    {
        self::assertIsArray($value);
        self::assertNotSame([], $value);
        $runners = [];
        foreach ($value as $runner) {
            self::assertIsArray($runner);
            $id = $runner['id'] ?? null;
            self::assertIsString($id);
            self::assertNotSame('', $id);
            self::assertArrayNotHasKey($id, $runners, "Duplicate G-07 runner {$id}");
            $command = $runner['command'] ?? null;
            self::assertIsString($command, "{$id}: command");
            self::assertNotSame('', $command, "{$id}: command");
            $normalized = [
                'command' => $command,
                'evidence_roots' => $this->stringList($runner['evidence_roots'] ?? null, "{$id}: evidence_roots"),
                'verification_files' => $this->stringList($runner['verification_files'] ?? null, "{$id}: verification_files"),
                'markers' => $this->stringList($runner['markers'] ?? null, "{$id}: markers"),
            ];
            $commandPath = $this->root . '/' . ltrim($command, './');
            self::assertFileExists($commandPath, $command);
            self::assertTrue(is_executable($commandPath), $command);
            $runners[$id] = $normalized;
        }

        return $runners;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $context): array
    {
        self::assertIsArray($value, $context);
        self::assertTrue(array_is_list($value), $context);
        self::assertNotSame([], $value, $context);
        $result = [];
        foreach ($value as $item) {
            self::assertIsString($item, $context);
            self::assertNotSame('', $item, $context);
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array{evidence_roots: list<string>} $runner
     */
    private static function pathBelongsToRunner(string $path, array $runner): bool
    {
        foreach ($runner['evidence_roots'] as $root) {
            if ($path === $root || str_starts_with($path, rtrim($root, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function ids(string $prefix, int $last): array
    {
        $ids = [];
        for ($number = 1; $number <= $last; ++$number) {
            $ids[] = sprintf('%s-%03d', $prefix, $number);
        }

        return $ids;
    }
}
