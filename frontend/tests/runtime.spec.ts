import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
  registerTenantDisposer,
  useTenantAuth,
  useTenantContext,
} from '@peanut-admin/admin/core'

import { createAdminRuntime } from '../src/app/runtime'
import { useWorkspaceStore } from '../src/app/store'

const config = {
  tenant: {
    baseUrl: 'https://tenant-api.example.test',
    allowedOrigin: 'https://tenant-api.example.test',
    clientKey: 'tenant-web',
  },
  platform: {
    baseUrl: 'https://platform-api.example.test',
    allowedOrigin: 'https://platform-api.example.test',
    clientKey: 'platform-web',
  },
}

const json = (body: unknown, status = 200): Response => new Response(JSON.stringify(body), {
  status,
  headers: { 'Content-Type': status >= 400 ? 'application/problem+json' : 'application/json' },
})

describe('reference admin runtime', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('invalidates and disposes old Tenant state before selecting another Tenant', async () => {
    const workspace = useWorkspaceStore()
    const context = useTenantContext()
    const auth = useTenantAuth()
    context.replace({
      audience: 'tenant',
      accountId: 'account-old',
      tenantId: 'tenant-old',
      memberId: 'member-old',
      moduleKeys: ['example.work-item'],
      permissionKeys: ['example.work-item.read'],
      authorizationRevision: '1',
    })
    auth.replaceAccessToken('old-token')
    workspace.tenantIdentity = {
      accountLabel: 'Old account',
      contextLabel: 'Old Tenant',
      actorLabel: 'Old member',
    }
    const dispose = vi.fn()
    const unregister = registerTenantDisposer('w02.runtime-test', dispose)
    const fetcher = vi.fn(async (request: Request) => {
      expect(request.url).toBe('https://tenant-api.example.test/api/v1/auth/tenants/select')
      expect(oldTicket.signal.aborted).toBe(true)
      expect(context.value).toBeNull()
      expect(workspace.tenantIdentity).toBeNull()
      expect(dispose).toHaveBeenCalledOnce()
      return json({ data: { state: 'authenticated', access_token: 'new-token' } })
    })
    const runtime = createAdminRuntime(config, { fetch: fetcher })
    const oldTicket = runtime.generation.capture()

    await runtime.selectTenant('challenge', 'tenant-new')
    unregister()

    expect(auth.accessToken).toBe('new-token')
    expect(oldTicket.isCurrent()).toBe(false)
  })

  it('does not install a late Tenant context after the audience changes', async () => {
    let resolveContext: ((response: Response) => void) | undefined
    const contextResponse = new Promise<Response>(resolve => { resolveContext = resolve })
    const fetcher = vi.fn(async (request: Request) => {
      if (request.url.endsWith('/api/v1/auth/context')) return contextResponse
      return json({ data: [] })
    })
    const runtime = createAdminRuntime(config, { fetch: fetcher })
    useTenantAuth().replaceAccessToken('tenant-token')
    await runtime.enterAudience('tenant')

    const loading = runtime.ensureContext('tenant')
    await runtime.enterAudience('platform')
    resolveContext?.(json({
      data: {
        audience: 'tenant',
        account_id: 'account-old',
        tenant_id: 'tenant-old',
        tenant_member_id: 'member-old',
        module_keys: [],
        permission_keys: [],
        authorization_revision: '1',
      },
    }))
    await loading

    expect(useTenantContext().value).toBeNull()
    expect(useWorkspaceStore().tenantIdentity).toBeNull()
  })
})
