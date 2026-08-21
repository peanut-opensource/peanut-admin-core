import { runAdminRouteGuard } from '@peanut-admin/admin/core'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

beforeEach(() => {
  vi.doMock('@peanut-admin/admin/file-media', () => import('../../packages/web/file-media/src/index'))
})

afterEach(() => {
  vi.doUnmock('@peanut-admin/admin/file-media')
  vi.doUnmock('../src/app/runtime')
  vi.resetModules()
})

describe('file-media reference host route', () => {
  it('registers one guarded Tenant route without eagerly loading the app runtime', async () => {
    vi.doMock('../src/app/runtime', () => { throw new Error('APP_RUNTIME_IMPORTED_EAGERLY') })
    const { peanutFileMediaModule } = await import('../src/modules/peanut-file-media')
    expect(peanutFileMediaModule).toMatchObject({ key: 'peanut.file-media', disposeOnTenantChange: true })
    expect(peanutFileMediaModule.routes).toHaveLength(1)
    expect(peanutFileMediaModule.routes[0]).toMatchObject({
      name: 'peanut.file-media.list', path: '/app/files',
      access: { moduleKey: 'peanut.file-media', permissionKeys: ['peanut.file-media.read'] },
    })
  }, 15_000)

  it('does not load the chunk when Module or permission checks fail closed', async () => {
    const { peanutFileMediaModule } = await import('../src/modules/peanut-file-media')
    const route = peanutFileMediaModule.routes[0]!
    const load = vi.spyOn(route, 'component')
    const dependencies = {
      enterAudience: vi.fn(async () => undefined), ensureContext: vi.fn(async () => undefined),
      loadNavigation: vi.fn(async () => undefined), hasPermissions: vi.fn(() => false), hasModule: vi.fn(() => true),
    }
    await expect(runAdminRouteGuard({ audience: 'tenant', moduleKey: route.access.moduleKey, permissionKeys: route.access.permissionKeys }, dependencies))
      .resolves.toMatchObject({ status: 'forbidden' })
    await expect(runAdminRouteGuard({ audience: 'tenant', moduleKey: route.access.moduleKey, permissionKeys: route.access.permissionKeys }, { ...dependencies, hasModule: () => false, hasPermissions: () => true }))
      .resolves.toMatchObject({ status: 'module-unavailable' })
    expect(load).not.toHaveBeenCalled()
  })
})
