import { describe, expect, it } from 'vitest'

import {
  APP_NAVIGATION,
  APP_ROUTE_REGISTRY,
  audienceForPath,
  resolveMenuDestination,
  safeReturnTo,
} from '../src/app/routes'

describe('reference admin route contract', () => {
  it('registers every P0 workspace route at build time', () => {
    for (const routeName of [
      'tenant.members.list',
      'tenant.members.effective-access',
      'tenant.departments.list',
      'tenant.roles.list',
      'tenant.modules.list',
      'tenant.audit.list',
      'platform.tenants.list',
      'platform.operators.list',
      'platform.roles.list',
      'platform.audit.list',
      'example-work-item-list',
      'example-work-item-policy',
    ]) {
      expect(APP_ROUTE_REGISTRY.has(routeName), routeName).toBe(true)
    }
    expect(APP_ROUTE_REGISTRY.get('tenant.members.effective-access')).toEqual({
      name: 'tenant.members.effective-access',
      path: '/app/members/:member_id/effective-access',
      audience: 'tenant',
      permission: 'core.member.effective-access.read',
    })
  })

  it('uses route_name instead of a backend-provided path or component', () => {
    expect(resolveMenuDestination({
      route_name: 'example-work-item-list',
      route_path: '/examples/work-items',
      component: 'server/InjectedComponent.vue',
    })).toBe('/app/examples/work-items')
    expect(resolveMenuDestination({
      route_name: 'unknown-route',
      route_path: '/app/members',
      component: 'tenant.members.list',
    })).toBeNull()
    expect(APP_NAVIGATION.diagnostics()).toContain('unknown-route')
  })

  it('accepts return_to only inside the same protected audience', () => {
    expect(safeReturnTo('/app/members?status=active', 'tenant')).toBe('/app/members?status=active')
    expect(safeReturnTo('/platform/tenants', 'tenant')).toBe('/app')
    expect(safeReturnTo('//evil.example/app', 'tenant')).toBe('/app')
    expect(safeReturnTo('/app/members', 'platform')).toBe('/platform')
    expect(audienceForPath('/app/members')).toBe('tenant')
    expect(audienceForPath('/platform/tenants')).toBe('platform')
  })
})
