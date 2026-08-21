import { createProtectedFetch } from '@peanut-admin/admin/core'
import type { RefreshCoordinator } from '@peanut-admin/admin/core'

export interface TenantClientDefinition {
  key: string
  apiPrefix: string
}

export const tenantClients = [
  { key: 'operations-web', apiPrefix: '/api/operations/v1/' },
  { key: 'reporting-web', apiPrefix: '/api/reporting/v1/' },
] as const satisfies readonly TenantClientDefinition[]

export interface TenantClientTransportOptions {
  baseUrl: string
  fetch?: (request: Request) => Promise<Response>
  getAccessToken: () => string | null
  setAccessToken: (token: string) => void
  refresh: () => Promise<string | null>
  refreshCoordinator?: RefreshCoordinator
}

export const createTenantClientTransport = (
  definition: TenantClientDefinition,
  options: TenantClientTransportOptions,
): ((request: Request) => Promise<Response>) => createProtectedFetch({
  ...options,
  refreshScope: `${definition.key}:tenant`,
  isAllowedPath: pathname => pathname.startsWith(definition.apiPrefix),
  isCredentialExchange: pathname => /\/auth\/(?:login|refresh|tenants\/select)$/.test(pathname),
})
