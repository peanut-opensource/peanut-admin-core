import { createIntegrationSecurityModuleContribution, createIntegrationSecurityRuntime } from '@peanut-admin/admin/integration-security'
import type { IntegrationSecurityPermissions, IntegrationSecurityRuntime, IntegrationSecurityTransport, TransportResult } from '@peanut-admin/admin/integration-security'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutIntegrationSecurityHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  integrationSecurityPermissions?: Partial<IntegrationSecurityPermissions>
}
export interface PeanutIntegrationSecurityHost { module: AdminModuleContribution; runtime: IntegrationSecurityRuntime }

const result = async (responsePromise: Promise<Response>): Promise<TransportResult> => {
  const response = await responsePromise
  const text = await response.text(); let body: unknown = null
  if (text !== '') { try { body = JSON.parse(text) as unknown } catch { body = null } }
  return { body, headers: response.headers, status: response.status }
}

export const createPeanutIntegrationSecurityHost = (options: PeanutIntegrationSecurityHostOptions): PeanutIntegrationSecurityHost => {
  const url = (path: string): string => new URL(path, options.baseUrl).toString()
  const request = (path: string, init: RequestInit): Promise<TransportResult> => result(options.fetch(new Request(url(path), { credentials: 'include', ...init })))
  const key = (): string => `security-${crypto.randomUUID()}`
  const write = (path: string, method: string, body: unknown, signal: AbortSignal, revision?: number): Promise<TransportResult> => request(path, { method, headers: { 'Content-Type': 'application/json', 'Idempotency-Key': key(), ...(revision === undefined ? {} : { 'If-Match': `"rev-${revision}"` }) }, body: JSON.stringify(body), signal })
  const transport: IntegrationSecurityTransport = {
    machines: signal => request('/api/v1/integration-security/machine-identities', { signal }),
    createMachine: (input, signal) => write('/api/v1/integration-security/machine-identities', 'POST', input, signal),
    rotateMachine: (identityKey, revision, signal) => write(`/api/v1/integration-security/machine-identities/${encodeURIComponent(identityKey)}/rotate`, 'POST', {}, signal, revision),
    revokeMachine: (identityKey, revision, signal) => write(`/api/v1/integration-security/machine-identities/${encodeURIComponent(identityKey)}`, 'DELETE', {}, signal, revision),
    webhooks: signal => request('/api/v1/integration-security/webhooks', { signal }),
    createWebhook: (input, signal) => write('/api/v1/integration-security/webhooks', 'POST', input, signal),
    rotateWebhook: (endpointKey, revision, signal) => write(`/api/v1/integration-security/webhooks/${encodeURIComponent(endpointKey)}/rotate-secret`, 'POST', {}, signal, revision),
    disableWebhook: (endpointKey, revision, signal) => write(`/api/v1/integration-security/webhooks/${encodeURIComponent(endpointKey)}`, 'DELETE', {}, signal, revision),
    deliveries: (page, pageSize, signal) => request(`/api/v1/integration-security/deliveries?page=${page}&page_size=${pageSize}`, { signal }),
    deliveryAttempts: (deliveryKey, page, pageSize, signal) => request(`/api/v1/integration-security/deliveries/${encodeURIComponent(deliveryKey)}/attempts?page=${page}&page_size=${pageSize}`, { signal }),
    sessions: signal => request('/api/v1/integration-security/sessions', { signal }),
    revokeSession: (sessionKey, signal) => write(`/api/v1/integration-security/sessions/${encodeURIComponent(sessionKey)}/revoke`, 'POST', {}, signal),
  }
  const denied = (): boolean => false
  const permissions: IntegrationSecurityPermissions = {
    canReadMachines: options.integrationSecurityPermissions?.canReadMachines ?? denied,
    canManageMachines: options.integrationSecurityPermissions?.canManageMachines ?? denied,
    canReadWebhooks: options.integrationSecurityPermissions?.canReadWebhooks ?? denied,
    canManageWebhooks: options.integrationSecurityPermissions?.canManageWebhooks ?? denied,
    canReadDeliveries: options.integrationSecurityPermissions?.canReadDeliveries ?? denied,
    canReadSessions: options.integrationSecurityPermissions?.canReadSessions ?? denied,
    canRevokeSession: options.integrationSecurityPermissions?.canRevokeSession ?? denied,
  }
  const runtime = createIntegrationSecurityRuntime({ transport, permissions })
  return { module: createIntegrationSecurityModuleContribution(runtime), runtime }
}
