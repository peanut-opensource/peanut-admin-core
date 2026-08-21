import { createMemoryRefreshCoordinator } from '@peanut-admin/admin/core'
import { describe, expect, it, vi } from 'vitest'

import { createTenantClientTransport, tenantClients } from '../src/clients'

describe('internal starter Tenant Clients', () => {
  it('defines independent build-time Client keys and API prefixes', () => {
    expect(tenantClients.map(client => client.key)).toEqual(['operations-web', 'reporting-web'])
    expect(new Set(tenantClients.map(client => client.apiPrefix)).size).toBe(2)
  })

  it('builds a protected transport for an application OpenAPI client', async () => {
    let token = 'access-token'
    const fetcher = vi.fn(async () => new Response(JSON.stringify({ data: [] }), { status: 200 }))
    const transport = createTenantClientTransport(tenantClients[0], {
      baseUrl: 'https://example.test',
      fetch: fetcher,
      getAccessToken: () => token,
      setAccessToken: value => { token = value },
      refresh: async () => 'rotated-token',
      refreshCoordinator: createMemoryRefreshCoordinator(),
    })

    const response = await transport(new Request('https://example.test/api/operations/v1/items'))

    expect(response.ok).toBe(true)
    expect(fetcher).toHaveBeenCalledOnce()
    await expect(
      transport(new Request('https://example.test/api/reporting/v1/items')),
    ).rejects.toThrow('API_AUDIENCE_MISMATCH')
  })

  it('rejects a different API origin before invoking starter credentials or transport', async () => {
    const fetcher = vi.fn(async () => new Response(null, { status: 200 }))
    const getAccessToken = vi.fn(() => 'access-token')
    const setAccessToken = vi.fn()
    const refresh = vi.fn(async () => 'rotated-token')
    const transport = createTenantClientTransport(tenantClients[0], {
      baseUrl: 'https://example.test',
      fetch: fetcher,
      getAccessToken,
      setAccessToken,
      refresh,
      refreshCoordinator: createMemoryRefreshCoordinator(),
    })

    await expect(
      transport(new Request('https://other.test/api/operations/v1/items')),
    ).rejects.toThrow('API_ORIGIN_MISMATCH')

    expect(getAccessToken).not.toHaveBeenCalled()
    expect(setAccessToken).not.toHaveBeenCalled()
    expect(refresh).not.toHaveBeenCalled()
    expect(fetcher).not.toHaveBeenCalled()
  })
})
