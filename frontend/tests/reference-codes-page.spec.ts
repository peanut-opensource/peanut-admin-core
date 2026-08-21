import { runAdminRouteGuard } from '@peanut-admin/admin/core'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

beforeEach(() => {
  vi.doMock('@peanut-admin/admin/reference-codes', () => import('../../packages/web/reference-codes/src/index'))
})

afterEach(() => {
  vi.doUnmock('@peanut-admin/admin/reference-codes')
  vi.doUnmock('../src/app/runtime')
  vi.resetModules()
})

describe('reference-code reference host route', () => {
  it('registers the guarded Tenant route without eagerly importing the circular app runtime', async () => {
    vi.doMock('../src/app/runtime', () => {
      throw new Error('APP_RUNTIME_IMPORTED_EAGERLY')
    })
    const { peanutReferenceCodesModule } = await import('../src/modules/peanut-reference-codes')
    const route = peanutReferenceCodesModule.routes[0]!

    expect(peanutReferenceCodesModule).toMatchObject({
      key: 'peanut.reference-codes',
      disposeOnTenantChange: true,
    })
    expect(peanutReferenceCodesModule.routes).toHaveLength(1)
    expect(route).toMatchObject({
      name: 'peanut.reference-codes.list',
      path: '/app/reference-codes',
      access: {
        moduleKey: 'peanut.reference-codes',
        permissionKeys: ['peanut.reference-codes.read'],
      },
    })
    expect(peanutReferenceCodesModule.routes.some(candidate => candidate.path.startsWith('/platform/'))).toBe(false)
  })

  it('does not load the page chunk when Module or permission checks fail closed', async () => {
    const { peanutReferenceCodesModule } = await import('../src/modules/peanut-reference-codes')
    const route = peanutReferenceCodesModule.routes[0]!
    const loadPage = vi.spyOn(route, 'component')
    const baseDependencies = {
      enterAudience: vi.fn(async () => undefined),
      ensureContext: vi.fn(async () => undefined),
      loadNavigation: vi.fn(async () => undefined),
      hasPermissions: vi.fn(() => false),
      hasModule: vi.fn(() => true),
    }

    await expect(runAdminRouteGuard({
      audience: 'tenant',
      moduleKey: route.access.moduleKey,
      permissionKeys: route.access.permissionKeys,
    }, baseDependencies)).resolves.toMatchObject({ status: 'forbidden' })
    await expect(runAdminRouteGuard({
      audience: 'tenant',
      moduleKey: route.access.moduleKey,
      permissionKeys: route.access.permissionKeys,
    }, { ...baseDependencies, hasModule: () => false, hasPermissions: () => true }))
      .resolves.toMatchObject({ status: 'module-unavailable' })
    expect(loadPage).not.toHaveBeenCalled()
  })
})
