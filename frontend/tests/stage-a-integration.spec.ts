import { describe, expect, it } from 'vitest'

import { APP_ROUTE_REGISTRY, TRUSTED_MENU_ROUTE_CONTRACTS } from '../src/app/routes'

describe('Stage A shared route registry', () => {
  it('binds manifest route names to one trusted tenant workbench and read-only upgrade page', () => {
    expect(APP_ROUTE_REGISTRY.get('tenant.governance.workbench')).toMatchObject({
      path: '/app/governance', audience: 'tenant', permission: 'core.role.read',
    })
    expect(APP_ROUTE_REGISTRY.get('platform.upgrade.status')).toMatchObject({
      path: '/platform/upgrade', audience: 'platform', permission: 'platform.upgrade.read',
    })
    expect(APP_ROUTE_REGISTRY.get('platform.governance.workbench')).toMatchObject({
      path: '/platform/governance', audience: 'platform', permission: 'platform.role.read',
    })
    expect(TRUSTED_MENU_ROUTE_CONTRACTS['peanut.file-media.list']).toEqual({
      componentKey: 'peanut.file-media.page', clientKeys: ['admin-web'],
    })
  })
})
