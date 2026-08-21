<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PeanutAdmin\Kernel\Module\ManifestSchemaValidator;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class OpisManifestSchemaValidator implements ManifestSchemaValidator
{
    public function __construct(private string $schemaPath) {}

    public function assertValid(object $manifest): void
    {
        try {
            $schema = json_decode((string) file_get_contents($this->schemaPath), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Module manifest schema is invalid.');
        }
        $result = (new Validator())->validate($manifest, $schema);
        if (!$result->isValid()) {
            $error = $result->error();
            if ($error === null) {
                throw new ModuleException('MODULE_MANIFEST_INVALID', 'Manifest validation failed without error details.');
            }
            $details = (new ErrorFormatter())->formatKeyed($error);
            throw new ModuleException(
                'MODULE_MANIFEST_INVALID',
                'Module manifest failed JSON Schema validation: '
                . json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
        }
    }
}
