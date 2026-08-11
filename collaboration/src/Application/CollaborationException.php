<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Application;

use RuntimeException;

final class CollaborationException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $message = 'The collaboration request is invalid.'): self
    {
        return new self('COLLABORATION_INVALID', 422, $message);
    }

    public static function notFound(): self
    {
        return new self('COLLABORATION_NOT_FOUND', 404, 'The collaboration target is unavailable.');
    }

    public static function denied(): self
    {
        return new self('COLLABORATION_DENIED', 403, 'The collaboration request is denied.');
    }

    public static function conflict(): self
    {
        return new self('COLLABORATION_CONFLICT', 409, 'The collaboration session has changed.');
    }

    public static function leaseExpired(): self
    {
        return new self('COLLABORATION_LEASE_EXPIRED', 409, 'The collaboration participant lease has expired.');
    }

    public static function payloadTooLarge(): self
    {
        return new self('COLLABORATION_PAYLOAD_TOO_LARGE', 413, 'The collaboration payload exceeds its policy.');
    }

    public static function backpressure(): self
    {
        return new self('COLLABORATION_BACKPRESSURE', 429, 'A collaboration snapshot is required before more updates.');
    }

    public static function providerUnavailable(): self
    {
        return new self('COLLABORATION_PROVIDER_UNAVAILABLE', 503, 'A collaboration provider is unavailable.');
    }

    public static function integrityFailure(): self
    {
        return new self('COLLABORATION_INTEGRITY_FAILURE', 500, 'Collaboration data failed its integrity check.');
    }

    public static function internal(): self
    {
        return new self('COLLABORATION_INTERNAL_ERROR', 500, 'The collaboration operation could not be completed.');
    }
}
