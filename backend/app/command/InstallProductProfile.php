<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

final readonly class InstallProductProfile
{
    /**
     * @param list<array{module_key: string, config: array<string, mixed>}> $modules
     * @param list<string> $roleTemplates
     * @param array{code: string, name: string}|null $defaultDepartment
     */
    private function __construct(
        public string $key,
        public string $name,
        public array $modules,
        public array $roleTemplates,
        public ?array $defaultDepartment,
    ) {}

    public static function load(string $path, string $schemaPath): self
    {
        try {
            $document = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
            $schema = json_decode((string) file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('PRODUCT_PROFILE_INVALID: malformed JSON.', 0, $exception);
        }
        if (!is_object($document) || !is_object($schema)) {
            throw new RuntimeException('PRODUCT_PROFILE_INVALID: document and schema must be objects.');
        }

        $result = (new Validator())->validate($document, $schema);
        if (!$result->isValid()) {
            $error = $result->error();
            $details = $error === null ? [] : (new ErrorFormatter())->formatKeyed($error);
            throw new RuntimeException(
                'PRODUCT_PROFILE_INVALID: '
                . json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
        }

        /** @var array<string, mixed> $data */
        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $modules = [];
        $seen = [];
        foreach ($data['modules'] as $module) {
            $moduleKey = (string) $module['module_key'];
            if (isset($seen[$moduleKey])) {
                throw new RuntimeException("PRODUCT_PROFILE_INVALID: duplicate module {$moduleKey}.");
            }
            $seen[$moduleKey] = true;
            $modules[] = ['module_key' => $moduleKey, 'config' => $module['config']];
        }

        return new self(
            (string) $data['key'],
            (string) $data['name'],
            $modules,
            array_values($data['role_templates'] ?? []),
            isset($data['default_department']) ? $data['default_department'] : null,
        );
    }

    /** @return list<string> */
    public function moduleKeys(): array
    {
        return array_column($this->modules, 'module_key');
    }

    /** @return array<string, mixed> */
    public function moduleConfig(string $moduleKey): array
    {
        foreach ($this->modules as $module) {
            if ($module['module_key'] === $moduleKey) {
                return $module['config'];
            }
        }

        throw new RuntimeException("PRODUCT_PROFILE_MODULE_MISSING: {$moduleKey}.");
    }
}
