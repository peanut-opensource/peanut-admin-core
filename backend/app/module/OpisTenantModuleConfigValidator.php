<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;

final class OpisTenantModuleConfigValidator implements TenantModuleConfigValidator
{
    public function assertValid(ManifestDocument $manifest, array $config): void
    {
        $backend = $manifest->data['backend'] ?? null;
        $relativePath = is_array($backend) ? ($backend['config_schema'] ?? null) : null;
        if ($relativePath === null) {
            if ($config !== []) {
                throw new ModuleException(
                    'MODULE_CONFIG_INVALID',
                    'A Module without config_schema accepts only an empty configuration.',
                );
            }

            return;
        }
        if (!is_string($relativePath) || $relativePath === '' || str_starts_with($relativePath, '/')) {
            throw new ModuleException('MODULE_CONFIG_INVALID', 'Module config_schema path is invalid.');
        }
        $path = realpath($manifest->root . '/' . $relativePath);
        if ($path === false || !str_starts_with($path, $manifest->root . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('MODULE_CONFIG_INVALID', 'Module config_schema is outside the Module root.');
        }
        try {
            $schema = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
            $value = json_decode(
                json_encode((object) $config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                false,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new ModuleException('MODULE_CONFIG_INVALID', 'Module configuration schema or value is invalid JSON.');
        }
        $result = (new Validator())->validate($value, $schema);
        if ($result->isValid()) {
            return;
        }
        $error = $result->error();
        $details = $error === null ? [] : (new ErrorFormatter())->formatKeyed($error);
        throw new ModuleException(
            'MODULE_CONFIG_INVALID',
            'Module configuration failed JSON Schema validation: '
            . json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }
}
