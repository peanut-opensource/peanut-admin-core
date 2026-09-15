<?php

declare(strict_types=1);

namespace PeanutAdmin\App\importexport;

use PeanutAdmin\FileMedia\Application\FileService;
use PeanutAdmin\ImportExport\Application\ImportExportException;
use PeanutAdmin\ImportExport\File\FileMediaGateway;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use Throwable;

final readonly class ThinkPhpFileMediaGateway implements FileMediaGateway
{
    public function __construct(private FileService $files) {}

    public function openCsvInput(AuthorizedOperationContext $context, string $fileKey)
    {
        try {
            return $this->files->content($context->tenantContext, $fileKey);
        } catch (Throwable) {
            throw ImportExportException::fileUnavailable();
        }
    }
    public function storePrivateCsv(AuthorizedOperationContext $context, string $operationKey, string $purpose, string $filename, $stream): string
    {
        if (!is_resource($stream) || !in_array($purpose, ['result','errors'], true)) {
            throw ImportExportException::fileUnavailable();
        }
        $temporary = tempnam(sys_get_temp_dir(), 'peanut-iox-');
        if (!is_string($temporary)) {
            throw ImportExportException::fileUnavailable();
        }
        try {
            $output = fopen($temporary, 'wb');
            if (!is_resource($output)) {
                throw ImportExportException::fileUnavailable();
            }try {
                if (!is_int(stream_copy_to_stream($stream, $output)) || !fflush($output)) {
                    throw ImportExportException::fileUnavailable();
                }
            } finally {
                fclose($output);
            }return $this->files->upload($context->tenantContext, $temporary, $filename)->fileKey;
        } catch (Throwable $e) {
            if ($e instanceof ImportExportException) {
                throw $e;
            }throw ImportExportException::fileUnavailable();
        } finally {
            @unlink($temporary);
        }
    }
}
