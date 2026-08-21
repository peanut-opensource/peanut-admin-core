<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use RuntimeException;

final readonly class InstallEnvironmentChecker
{
    /** @param list<string>|null $extensions */
    public function __construct(
        private string $root,
        private string $phpVersion = PHP_VERSION,
        private ?array $extensions = null,
    ) {}

    /** @return array{ready: bool, errors: list<string>} */
    public function check(): array
    {
        $errors = [];
        if (version_compare($this->phpVersion, '8.3.0', '<')) {
            $errors[] = 'PHP 8.3 or newer is required.';
        }

        $extensions = array_map('strtolower', $this->extensions ?? get_loaded_extensions());
        foreach (['json', 'pdo', 'pdo_mysql', 'openssl', 'mbstring'] as $required) {
            if (!in_array($required, $extensions, true)) {
                $errors[] = "Required PHP extension is missing: {$required}.";
            }
        }

        foreach ([
            'vendor/autoload.php',
            'composer.lock',
            'pnpm-lock.yaml',
            'schemas/product-profile.schema.json',
        ] as $requiredPath) {
            if (!is_readable($this->root . '/' . $requiredPath)) {
                $errors[] = "Required installation asset is not readable: {$requiredPath}.";
            }
        }

        return ['ready' => $errors === [], 'errors' => $errors];
    }

    public function assertReady(): void
    {
        $report = $this->check();
        if (!$report['ready']) {
            throw new RuntimeException('INSTALL_ENVIRONMENT_INVALID: ' . implode(' ', $report['errors']));
        }
    }
}
