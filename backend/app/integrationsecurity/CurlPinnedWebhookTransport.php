<?php

declare(strict_types=1);

namespace PeanutAdmin\App\integrationsecurity;

use PeanutAdmin\IntegrationSecurity\Application\IntegrationSecurityException;
use PeanutAdmin\IntegrationSecurity\Webhook\WebhookRequest;
use PeanutAdmin\IntegrationSecurity\Webhook\WebhookResponse;
use PeanutAdmin\IntegrationSecurity\Webhook\WebhookTransport;

final class CurlPinnedWebhookTransport implements WebhookTransport
{
    public function send(WebhookRequest $request): WebhookResponse
    {
        if (!function_exists('curl_init')) {
            throw IntegrationSecurityException::destinationDenied();
        }
        $address = $request->destination->approvedAddresses[0];
        $handle = curl_init($request->destination->url);
        if ($handle === false) {
            throw IntegrationSecurityException::destinationDenied();
        }
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $started = hrtime(true);
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $request->body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => $request->timeoutSeconds,
                CURLOPT_TIMEOUT => $request->timeoutSeconds,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_RESOLVE => [$request->destination->host . ':' . $request->destination->port . ':' . $address],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            if (curl_exec($handle) === false) {
                throw IntegrationSecurityException::destinationDenied();
            }
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $duration = min(30000, (int) ((hrtime(true) - $started) / 1_000_000));
            return new WebhookResponse($status, $duration);
        } finally {
            curl_close($handle);
        }
    }
}
