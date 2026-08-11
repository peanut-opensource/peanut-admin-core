<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Contract;

use InvalidArgumentException;

final readonly class CollaborationSubmission
{
    public function __construct(
        public string $payloadSchemaKey,
        public string $payloadSchemaVersion,
        public string $payloadRef,
        public string $payloadSha256,
        public ?string $attachmentManifestSha256 = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $payloadSchemaKey) !== 1
            || strlen($payloadSchemaKey) > 64
            || preg_match('/^[\x21-\x7E]+$/D', $payloadSchemaVersion) !== 1
            || strlen($payloadSchemaVersion) > 32
            || preg_match('/^[\x21-\x7E]+$/D', $payloadRef) !== 1
            || strlen($payloadRef) > 512
            || preg_match('/^[0-9a-f]{64}$/D', $payloadSha256) !== 1
            || ($attachmentManifestSha256 !== null
                && preg_match('/^[0-9a-f]{64}$/D', $attachmentManifestSha256) !== 1)) {
            throw new InvalidArgumentException('The collaboration submission is invalid.');
        }
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'payload_schema_key' => $this->payloadSchemaKey,
            'payload_schema_version' => $this->payloadSchemaVersion,
            'payload_ref' => $this->payloadRef,
            'payload_sha256' => $this->payloadSha256,
            'attachment_manifest_sha256' => $this->attachmentManifestSha256,
        ];
    }
}
