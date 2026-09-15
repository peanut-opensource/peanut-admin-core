<?php

declare(strict_types=1);

namespace PeanutAdmin\App\integrationsecurity;

use DateTimeImmutable;
use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\IntegrationSecurity\Application\IntegrationSecurityException;
use PeanutAdmin\IntegrationSecurity\Application\MachineIdentity;
use PeanutAdmin\IntegrationSecurity\Application\MachineIdentityService;
use PeanutAdmin\IntegrationSecurity\Application\SessionDevice;
use PeanutAdmin\IntegrationSecurity\Application\SessionSecurityService;
use PeanutAdmin\IntegrationSecurity\Application\WebhookDeliveryLogService;
use PeanutAdmin\IntegrationSecurity\Application\WebhookEndpoint;
use PeanutAdmin\IntegrationSecurity\Application\WebhookService;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use think\Request;
use think\Response;

final class IntegrationSecurityHttpRuntime
{
    public function __construct(
        private readonly MachineIdentityService $machines,
        private readonly WebhookService $webhooks,
        private readonly WebhookDeliveryLogService $deliveries,
        private readonly SessionSecurityService $sessions,
        private readonly TenantModuleRuntime $runtime,
    ) {}

    public function machines(Request $request): Response
    {
        return $this->read($request, 'listMachineIdentities', '/api/v1/integration-security/machine-identities', 'machine.read', 'machine-read', fn($context) => array_map(self::machine(...), $this->machines->list($context)));
    }
    public function webhooks(Request $request): Response
    {
        return $this->read($request, 'listWebhookEndpoints', '/api/v1/integration-security/webhooks', 'webhook.read', 'webhook-read', fn($context) => array_map(self::webhook(...), $this->webhooks->list($context)));
    }
    public function sessions(Request $request): Response
    {
        return $this->read($request, 'listIntegrationSessions', '/api/v1/integration-security/sessions', 'session.read', 'session-read', fn($context) => array_map(self::session(...), $this->sessions->list($context)));
    }

    public function deliveries(Request $request): Response
    {
        return $this->page($request, 'listWebhookDeliveries', '/api/v1/integration-security/deliveries', fn($context, int $page, int $size) => $this->deliveries->deliveries($context, $page, $size));
    }

    public function attempts(Request $request, string $deliveryKey): Response
    {
        return $this->page($request, 'listWebhookDeliveryAttempts', '/api/v1/integration-security/deliveries/{delivery_key}/attempts', fn($context, int $page, int $size) => $this->deliveries->attempts($context, $deliveryKey, $page, $size), '/api/v1/integration-security/deliveries/' . rawurlencode($deliveryKey) . '/attempts');
    }

    public function createMachine(Request $request): Response
    {
        return $this->command($request, 'createMachineIdentity', 'POST', '/api/v1/integration-security/machine-identities', '/api/v1/integration-security/machine-identities', 'machine.manage', 'machine-manage', function ($context, array $payload) {
            self::keys($payload, ['name','scopes','expires_at']);
            $result = $this->machines->create($context, self::string($payload, 'name'), self::strings($payload, 'scopes'), self::instant($payload['expires_at']));
            $identity = self::machine($result->identity);
            return [201, ['data' => ['identity' => $identity,'token' => $result->token]], ['data' => $identity], $result->identity->identityKey, $result->identity->revision];
        }, true);
    }

    public function rotateMachine(Request $request, string $identityKey): Response
    {
        return $this->command($request, 'rotateMachineIdentity', 'POST', '/api/v1/integration-security/machine-identities/{identity_key}/rotate', '/api/v1/integration-security/machine-identities/' . rawurlencode($identityKey) . '/rotate', 'machine.manage', 'machine-manage', function ($context, array $payload, int $revision) use ($identityKey) {
            self::keys($payload, []);
            $result = $this->machines->rotate($context, $identityKey, $revision);
            $identity = self::machine($result->identity);
            return [200, ['data' => ['identity' => $identity,'token' => $result->token]], ['data' => $identity], $result->identity->identityKey, $result->identity->revision];
        }, true);
    }

    public function revokeMachine(Request $request, string $identityKey): Response
    {
        return $this->command($request, 'revokeMachineIdentity', 'DELETE', '/api/v1/integration-security/machine-identities/{identity_key}', '/api/v1/integration-security/machine-identities/' . rawurlencode($identityKey), 'machine.manage', 'machine-manage', function ($context, array $payload, int $revision) use ($identityKey) {
            self::keys($payload, []);
            $identity = $this->machines->revoke($context, $identityKey, $revision);
            return [200,['data' => self::machine($identity)],null,$identity->identityKey,$identity->revision];
        });
    }

    public function createWebhook(Request $request): Response
    {
        return $this->command($request, 'createWebhookEndpoint', 'POST', '/api/v1/integration-security/webhooks', '/api/v1/integration-security/webhooks', 'webhook.manage', 'webhook-manage', function ($context, array $payload) {
            self::keys($payload, ['name','url','events']);
            $result = $this->webhooks->create($context, self::string($payload, 'name'), self::string($payload, 'url'), self::strings($payload, 'events'));
            $endpoint = self::webhook($result->endpoint);
            return [201,['data' => ['endpoint' => $endpoint,'signing_secret' => $result->signingSecret]],['data' => $endpoint],$result->endpoint->endpointKey,$result->endpoint->revision];
        }, true);
    }

    public function rotateWebhook(Request $request, string $endpointKey): Response
    {
        return $this->command($request, 'rotateWebhookSecret', 'POST', '/api/v1/integration-security/webhooks/{endpoint_key}/rotate-secret', '/api/v1/integration-security/webhooks/' . rawurlencode($endpointKey) . '/rotate-secret', 'webhook.manage', 'webhook-manage', function ($context, array $payload, int $revision) use ($endpointKey) {
            self::keys($payload, []);
            $result = $this->webhooks->rotateSecret($context, $endpointKey, $revision);
            $endpoint = self::webhook($result->endpoint);
            return [200,['data' => ['endpoint' => $endpoint,'signing_secret' => $result->signingSecret]],['data' => $endpoint],$endpointKey,$result->endpoint->revision];
        }, true);
    }

    public function disableWebhook(Request $request, string $endpointKey): Response
    {
        return $this->command($request, 'disableWebhookEndpoint', 'DELETE', '/api/v1/integration-security/webhooks/{endpoint_key}', '/api/v1/integration-security/webhooks/' . rawurlencode($endpointKey), 'webhook.manage', 'webhook-manage', function ($context, array $payload, int $revision) use ($endpointKey) {
            self::keys($payload, []);
            $endpoint = $this->webhooks->disable($context, $endpointKey, $revision);
            return [200,['data' => self::webhook($endpoint)],null,$endpointKey,$endpoint->revision];
        });
    }

    public function revokeSession(Request $request, string $sessionKey): Response
    {
        return $this->command($request, 'revokeIntegrationSession', 'POST', '/api/v1/integration-security/sessions/{session_key}/revoke', '/api/v1/integration-security/sessions/' . rawurlencode($sessionKey) . '/revoke', 'session.revoke', 'session-revoke', function ($context, array $payload) use ($sessionKey) {
            self::keys($payload, []);
            $session = $this->sessions->revoke($context, $sessionKey);
            return [200,['data' => self::session($session)],null,$sessionKey,1];
        });
    }

    private function read(Request $request, string $id, string $path, string $permission, string $operation, callable $handler): Response
    {
        $op = TenantModuleRuntime::operation($id, 'GET', $path, 'peanut.integration-security', 'peanut.integration-security.' . $permission);
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = $this->runtime->host()->read($op, $external, static function ($authorized, $query) use ($handler, $operation) {
            try {
                self::keys($query->body['payload'] ?? null, []);
                if (($query->body['query'] ?? null) !== []) {
                    throw IntegrationSecurityException::invalid();
                }return new ExternalOperationResponse(200, ['data' => ['items' => $handler(TenantModuleRuntime::authorizedContext($authorized, 'peanut.integration-security', $operation))]]);
            } catch (IntegrationSecurityException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    private function page(Request $request, string $id, string $template, callable $handler, ?string $path = null): Response
    {
        $path ??= $template;
        $op = TenantModuleRuntime::operation($id, 'GET', $template, 'peanut.integration-security', 'peanut.integration-security.delivery.read');
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = $this->runtime->host()->read($op, $external, static function ($authorized, $query) use ($handler) {
            try {
                self::keys($query->body['payload'] ?? null, []);
                $q = $query->body['query'] ?? null;
                if (!is_array($q) || array_diff(array_keys($q), ['page','page_size']) !== []) {
                    throw IntegrationSecurityException::invalid();
                }$page = $handler(TenantModuleRuntime::authorizedContext($authorized, 'peanut.integration-security', 'delivery-read'), TenantModuleRuntime::positiveInt($q['page'] ?? '1', 10000), TenantModuleRuntime::positiveInt($q['page_size'] ?? '20', 100));
                return new ExternalOperationResponse(200, ['data' => $page->jsonSerialize()]);
            } catch (IntegrationSecurityException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    private function command(Request $request, string $id, string $method, string $template, string $path, string $permission, string $operation, callable $handler, bool $oneTime = false): Response
    {
        $op = TenantModuleRuntime::operation($id, $method, $template, 'peanut.integration-security', 'peanut.integration-security.' . $permission, true, true);
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = $this->runtime->host()->command($op, $external, static function ($authorized, $command) use ($handler, $operation, $id, $oneTime) {
            try {
                $payload = $command->body['payload'] ?? null;
                if (!is_array($payload) || ($command->body['query'] ?? null) !== []) {
                    throw IntegrationSecurityException::invalid();
                }$revision = TenantModuleRuntime::expectedRevision($command, true) ?? 1;
                [$status,$body,$replay,$key,$nextRevision] = $handler(TenantModuleRuntime::authorizedContext($authorized, 'peanut.integration-security', $operation), $payload, $revision);
                $headers = in_array($id, ['rotateMachineIdentity','rotateWebhookSecret'], true) ? ['ETag' => '"rev-' . $nextRevision . '"'] : [];
                return new ExternalOperationResult($status, $body, 'tenant.integration-security.changed', 'peanut.integration-security.' . $operation, ['operation' => $id,'revision' => $nextRevision], 'integration-security', $key, $oneTime ? $replay : null, $headers);
            } catch (IntegrationSecurityException $e) {
                throw self::problem($e);
            }
        }, guard: $this->runtime->commandGuard('peanut.integration-security'));
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    /** @return array{identity_key:string,name:string,scopes:list<string>,status:string,token_prefix:string,token_last_four:string,expires_at:?string,last_used_at:?string,revision:int,created_at:string} */
    private static function machine(MachineIdentity $v): array
    {
        return ['identity_key' => $v->identityKey,'name' => $v->name,'scopes' => $v->scopes,'status' => $v->status,'token_prefix' => $v->tokenPrefix,'token_last_four' => $v->tokenLastFour,'expires_at' => $v->expiresAt,'last_used_at' => $v->lastUsedAt,'revision' => $v->revision,'created_at' => $v->createdAt];
    }
    /** @return array{endpoint_key:string,name:string,url:string,events:list<string>,status:string,revision:int,created_at:string} */
    private static function webhook(WebhookEndpoint $v): array
    {
        return ['endpoint_key' => $v->endpointKey,'name' => $v->name,'url' => $v->url,'events' => $v->events,'status' => $v->status,'revision' => $v->revision,'created_at' => $v->createdAt];
    }
    /** @return array{session_key:string,client_key:string,status:string,current:bool,masked_ip:?string,user_agent_fingerprint:?string,issued_at:string,last_seen_at:string,absolute_expires_at:string,revoked_at:?string} */
    private static function session(SessionDevice $v): array
    {
        return ['session_key' => $v->sessionKey,'client_key' => $v->clientKey,'status' => $v->status,'current' => $v->current,'masked_ip' => $v->maskedIp,'user_agent_fingerprint' => $v->userAgentFingerprint,'issued_at' => $v->issuedAt,'last_seen_at' => $v->lastSeenAt,'absolute_expires_at' => $v->absoluteExpiresAt,'revoked_at' => $v->revokedAt];
    }
    /** @param list<string> $expected */
    private static function keys(mixed $payload, array $expected): void
    {
        if (!is_array($payload)) {
            throw IntegrationSecurityException::invalid();
        }$actual = array_keys($payload);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw IntegrationSecurityException::invalid();
        }
    }
    /** @param array<string,mixed> $p */
    private static function string(array $p, string $key): string
    {
        $v = $p[$key] ?? null;
        if (!is_string($v)) {
            throw IntegrationSecurityException::invalid();
        }return $v;
    }
    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private static function strings(array $p, string $key): array
    {
        $v = $p[$key] ?? null;
        if (!is_array($v) || !array_is_list($v)) {
            throw IntegrationSecurityException::invalid();
        }$result = [];
        foreach ($v as $item) {
            if (!is_string($item)) {
                throw IntegrationSecurityException::invalid();
            }$result[] = $item;
        }return $result;
    }
    private static function instant(mixed $v): ?DateTimeImmutable
    {
        if ($v === null) {
            return null;
        }if (!is_string($v)) {
            throw IntegrationSecurityException::invalid();
        }try {
            return new DateTimeImmutable($v);
        } catch (\Throwable) {
            throw IntegrationSecurityException::invalid();
        }
    }
    private static function problem(IntegrationSecurityException $e): ApiException
    {
        $status = match ($e->problemCode) {
            'INTEGRATION_PERMISSION_DENIED' => 403,'MACHINE_IDENTITY_NOT_FOUND','WEBHOOK_ENDPOINT_NOT_FOUND','SESSION_DEVICE_NOT_FOUND' => 404,'INTEGRATION_REVISION_CONFLICT' => 409,'WEBHOOK_DESTINATION_DENIED' => 422,default => 422,
        };
        return new ApiException($e->problemCode, $status, 'The integration security operation could not be completed.');
    }
}
