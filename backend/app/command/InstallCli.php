<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use PDO;
use Throwable;

final class InstallCli
{
    private function __construct() {}

    /** @param list<string> $arguments */
    public static function run(string $root, array $arguments): int
    {
        try {
            $options = self::options($arguments);
            $profilePath = $options['profile'] ?? 'profiles/reference-admin.json';
            $profile = InstallProductProfile::load(
                $root . '/' . ltrim($profilePath, '/'),
                $root . '/schemas/product-profile.schema.json',
            );
            $password = getenv('PEANUT_BOOTSTRAP_PASSWORD');
            if (!is_string($password) || $password === '') {
                throw new \RuntimeException('PEANUT_BOOTSTRAP_PASSWORD must be set.');
            }
            $tenant = null;
            if (isset($options['tenant-code'])) {
                $tenant = [
                    'code' => $options['tenant-code'],
                    'name' => $options['tenant-name'] ?? '',
                    'owner_email' => $options['tenant-owner-email'] ?? '',
                    'owner_name' => $options['tenant-owner-name'] ?? '',
                ];
                $tenantPassword = getenv('PEANUT_TENANT_OWNER_PASSWORD');
                if (is_string($tenantPassword) && $tenantPassword !== '') {
                    $tenant['owner_password'] = $tenantPassword;
                }
            }

            $result = (new InstallWorkflow($root, self::pdo()))->run(
                $profile,
                $options['email'] ?? '',
                $password,
                $options['display-name'] ?? '',
                $tenant,
                isset($options['allow-existing']),
            );
            fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /**
     * @param list<string> $arguments
     * @return array<string, string>
     */
    private static function options(array $arguments): array
    {
        $options = [];
        for ($index = 0; $index < count($arguments); ++$index) {
            $argument = $arguments[$index];
            if (!str_starts_with($argument, '--')) {
                throw new \RuntimeException("Unknown argument: {$argument}");
            }
            $name = substr($argument, 2);
            if ($name === 'allow-existing') {
                $options[$name] = '1';
                continue;
            }
            $value = $arguments[++$index] ?? null;
            if ($value === null || str_starts_with($value, '--')) {
                throw new \RuntimeException("Missing value for --{$name}.");
            }
            $options[$name] = $value;
        }

        return $options;
    }

    private static function pdo(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_DATABASE') ?: 'peanut_admin',
            ),
            getenv('DB_USERNAME') ?: 'peanut_admin',
            getenv('DB_PASSWORD') ?: 'peanut_admin_dev',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
