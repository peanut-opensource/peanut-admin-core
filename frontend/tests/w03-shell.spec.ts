// @vitest-environment happy-dom

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AdminShell, PlatformShell } from '@peanut-admin/admin/shell'

import { readShellHostConfig } from '../src/shell/host-config'
import StatusPage from '../src/pages/status/StatusPage.vue'
import WorkspaceLayout from '../src/shell/WorkspaceLayout.vue'

const mocks = vi.hoisted(() => ({
  beginTenantSwitch: vi.fn(),
  logout: vi.fn(),
  routeRegistry: new Map([
    ['tenant.members.list', {
      name: 'tenant.members.list',
      path: '/app/members',
      audience: 'tenant' as const,
    }],
    ['platform.tenants.list', {
      name: 'platform.tenants.list',
      path: '/platform/tenants',
      audience: 'platform' as const,
    }],
  ]),
  push: vi.fn(),
  replace: vi.fn(),
  route: {
    name: 'tenant.home',
    path: '/app',
    meta: { audience: 'tenant' as 'tenant' | 'platform', title: 'Workspace' },
    query: {} as Record<string, string>,
  },
  workspace: {
    activeAudience: 'tenant' as 'tenant' | 'platform',
    tenantIdentity: {
      accountLabel: 'tenant@example.test',
      contextLabel: 'Alpha Tenant',
      actorLabel: 'Tenant Owner',
    },
    platformIdentity: {
      accountLabel: 'platform@example.test',
      contextLabel: 'Platform',
      actorLabel: 'Platform Owner',
    },
    tenantMenus: [{
      key: 'tenant.members',
      type: 'page' as const,
      name: 'Members',
      route_name: 'tenant.members.list',
      icon: null,
      children: [],
    }],
    platformMenus: [{
      key: 'platform.tenants',
      type: 'page' as const,
      name: 'Tenants',
      route_name: 'platform.tenants.list',
      icon: null,
      children: [],
    }],
    shellCollapsed: false,
    mobileNavigationOpen: false,
    problem: null as null | {
      type: string
      title: string
      status: number
      detail: string
      instance: string
      code: string
      request_id: string
    },
    addMenuDiagnostic: vi.fn(),
  },
}))

vi.mock('../src/app/runtime', () => ({
  useAdminRuntime: () => ({
    beginTenantSwitch: mocks.beginTenantSwitch,
    logout: mocks.logout,
    routeRegistry: mocks.routeRegistry,
    workspaceShell: (audience: 'tenant' | 'platform') => audience === 'tenant' ? AdminShell : PlatformShell,
  }),
}))

vi.mock('../src/app/store', () => ({
  useWorkspaceStore: () => mocks.workspace,
}))

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : \'/\'"><slot /></a>',
  },
  RouterView: { template: '<div data-testid="router-view" />' },
  useRoute: () => mocks.route,
  useRouter: () => ({
    back: vi.fn(),
    push: mocks.push,
    replace: mocks.replace,
  }),
}))

const findButton = (wrapper: ReturnType<typeof mount>, label: string) => (
  wrapper.findAll('button').find(button => button.text() === label)
)

describe('W03 reference host shell', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.route.name = 'tenant.home'
    mocks.route.path = '/app'
    mocks.route.meta.audience = 'tenant'
    mocks.route.meta.title = 'Workspace'
    mocks.route.query = {}
    mocks.workspace.activeAudience = 'tenant'
    mocks.workspace.shellCollapsed = false
    mocks.workspace.mobileNavigationOpen = false
    mocks.workspace.problem = null
  })

  afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('reads only host-owned build-time presentation values', () => {
    expect(readShellHostConfig({
      VITE_ADMIN_BRAND_NAME: ' Customer Console ',
      VITE_ADMIN_BRAND_MARK: ' C ',
      VITE_ADMIN_TENANT_LABEL: ' Customer workspace ',
      VITE_ADMIN_PLATFORM_LABEL: ' Operations control ',
    })).toMatchObject({
      brand: { name: 'Customer Console', mark: 'C' },
      audiences: {
        tenant: { label: 'Customer workspace' },
        platform: { label: 'Operations control' },
      },
    })
  })

  it('delegates Tenant switch and logout to W02 without a Shell API request', async () => {
    const fetcher = vi.fn()
    vi.stubGlobal('fetch', fetcher)
    const wrapper = mount(WorkspaceLayout, { attachTo: document.body })

    await findButton(wrapper, '切换租户')?.trigger('click')
    await flushPromises()
    expect(mocks.beginTenantSwitch).toHaveBeenCalledOnce()
    expect(mocks.push).toHaveBeenCalledWith({ name: 'tenant.select', query: { return_to: '/app' } })

    await findButton(wrapper, '退出')?.trigger('click')
    await flushPromises()
    expect(mocks.logout).toHaveBeenCalledWith('tenant')
    expect(mocks.replace).toHaveBeenCalledWith('/login')
    expect(fetcher).not.toHaveBeenCalled()
  })

  it('keeps platform identity, navigation, and commands separate from Tenant mode', async () => {
    mocks.route.name = 'platform.home'
    mocks.route.path = '/platform'
    mocks.route.meta.audience = 'platform'
    mocks.route.meta.title = 'Platform'
    const wrapper = mount(WorkspaceLayout)

    expect(wrapper.get('.pa-shell').attributes('data-audience')).toBe('platform')
    expect(wrapper.text()).toContain('Platform Owner')
    expect(wrapper.text()).toContain('Tenants')
    expect(wrapper.text()).not.toContain('Members')
    expect(findButton(wrapper, '切换租户')).toBeUndefined()

    await findButton(wrapper, '退出')?.trigger('click')
    await flushPromises()
    expect(mocks.logout).toHaveBeenCalledWith('platform')
    expect(mocks.replace).toHaveBeenCalledWith('/platform/login')
  })

  it.each([
    [404, 'RESOURCE_NOT_FOUND', 'not-found', {}],
    [412, 'PRECONDITION_FAILED', 'conflict', {}],
    [429, 'RATE_LIMITED', 'rate-limit', { retry_after: '17' }],
    [503, 'MODULE_DISABLED', 'module-unavailable', {}],
    [503, 'SERVICE_UNAVAILABLE', 'service-unavailable', {}],
    [401, 'AUTH_SESSION_EXPIRED', 'session-expired', {}],
  ])('maps status %i and code %s to the explicit %s state', (status, code, expectedState, query) => {
    mocks.route.name = 'state.unavailable'
    mocks.route.query = query
    mocks.workspace.problem = {
      type: `/docs/problems/${code.toLowerCase()}`,
      title: 'Request failed',
      status,
      detail: `${code} detail`,
      instance: 'urn:request:req_w03_status',
      code,
      request_id: 'req_w03_status',
    }

    const wrapper = mount(StatusPage)

    expect(wrapper.get('[data-state]').attributes('data-state')).toBe(expectedState)
    expect(wrapper.text()).toContain('req_w03_status')
    if (status === 429) expect(wrapper.text()).toContain('17')
  })
})
