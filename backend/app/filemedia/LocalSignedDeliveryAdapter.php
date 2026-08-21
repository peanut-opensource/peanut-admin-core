<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use PeanutAdmin\FileMedia\Delivery\DeliveryAdapter;
use PeanutAdmin\FileMedia\Delivery\DeliveryGrant;
use PeanutAdmin\FileMedia\Delivery\DeliveryRequest;
use PeanutAdmin\FileMedia\Delivery\SignedDeliveryTokenService;

final readonly class LocalSignedDeliveryAdapter implements DeliveryAdapter
{
    public function __construct(private string $baseUrl, private SignedDeliveryTokenService $tokens)
    {
        if (preg_match('#^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$#D', $baseUrl) !== 1) {
            throw new \InvalidArgumentException('FILE_DELIVERY_BASE_URL_INVALID');
        }
    }
    public function key(): string
    {
        return 'local-signed';
    }
    public function supportsStorageProvider(string $providerKey): bool
    {
        return hash_equals('local-private', $providerKey);
    }
    public function issue(DeliveryRequest $request): DeliveryGrant
    {
        $tokenId = bin2hex(random_bytes(16));
        $token = $this->tokens->issue($request->context->tenantId, $request->file->fileKey, $request->visibility, $request->replayMode, $request->issuedAt, $request->ttlSeconds, $tokenId);
        return new DeliveryGrant($this->key(), $this->baseUrl . '/api/v1/file-deliveries/' . $request->file->fileKey . '?token=' . $token, $request->issuedAt->modify('+' . $request->ttlSeconds . ' seconds'), $request->visibility, $request->replayMode, $tokenId);
    }
}
