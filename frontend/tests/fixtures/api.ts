import { expect } from '@playwright/test'
import type { Page, Route, TestInfo } from '@playwright/test'

export type TargetFixtureMode = 'zero' | 'single' | 'multiple'

export interface FixtureProblem {
  status: number
  code: string
  detail: string
  retryAfter?: string
}

export interface ApiFixtureState {
  activeTenantId: string
  tenantToken: string
  platformToken: string
  tenantPermissions: string[]
  platformPermissions: string[]
  tenantModules: string[]
  targetMode: TargetFixtureMode
  includeUnknownMenu: boolean
  includeUnsafeMenu: boolean
  expireTenantAccess: boolean
  refreshCount: number
  refreshDelayMs: number
  memberDelayMs: number
  effectiveAccessEmpty: boolean
  policySelectionForbidden: boolean
  nextProblems: Map<string, FixtureProblem>
  requestCounts: Map<string, number>
  createCount: number
}

const tenantPermissions = [
  'core.member.read',
  'core.member.effective-access.read',
  'core.department.read',
  'core.role.read',
  'core.module.read',
  'core.audit.read',
  'example.target.read',
  'example.reference.read',
  'example.reference.use',
  'example.work-item.read',
  'example.work-item.create',
  'example.work-item.policy-publish',
]

const platformPermissions = [
  'platform.tenant.read',
  'platform.operator.read',
  'platform.role.read',
  'platform.audit.read',
]

export const createApiFixtureState = (overrides: Partial<ApiFixtureState> = {}): ApiFixtureState => ({
  activeTenantId: '101',
  tenantToken: 'tenant-access-1',
  platformToken: 'platform-access-1',
  tenantPermissions: [...tenantPermissions],
  platformPermissions: [...platformPermissions],
  tenantModules: ['core', 'example.target', 'example.reference', 'example.work-item'],
  targetMode: 'multiple',
  includeUnknownMenu: false,
  includeUnsafeMenu: false,
  expireTenantAccess: false,
  refreshCount: 0,
  refreshDelayMs: 100,
  memberDelayMs: 0,
  effectiveAccessEmpty: false,
  policySelectionForbidden: false,
  nextProblems: new Map(),
  requestCounts: new Map(),
  createCount: 0,
  ...overrides,
})

const sleep = async (milliseconds: number): Promise<void> => {
  await new Promise(resolve => setTimeout(resolve, milliseconds))
}

const requestId = 'req_browser_fixture'

const fulfillJson = async (
  route: Route,
  body: unknown,
  status = 200,
  headers: Record<string, string> = {},
): Promise<void> => {
  await route.fulfill({
    status,
    contentType: status >= 400 ? 'application/problem+json' : 'application/json',
    headers: {
      'X-Request-Id': requestId,
      'Cache-Control': 'no-store',
      ...headers,
    },
    body: body === null ? '' : JSON.stringify(body),
  })
}

const fulfillProblem = async (route: Route, problem: FixtureProblem): Promise<void> => {
  await fulfillJson(route, {
    type: `/docs/problems/${problem.code.toLowerCase().replaceAll('_', '-')}`,
    title: 'Request rejected',
    status: problem.status,
    detail: problem.detail,
    instance: `urn:request:${requestId}`,
    code: problem.code,
    request_id: requestId,
  }, problem.status, problem.retryAfter === undefined ? {} : { 'Retry-After': problem.retryAfter })
}

const authorization = (route: Route): string => route.request().headers().authorization ?? ''

const isTenantPublic = (path: string): boolean => [
  '/api/v1/auth/login',
  '/api/v1/auth/tenants/select',
  '/api/v1/auth/refresh',
].includes(path)

const isPlatformPublic = (path: string): boolean => [
  '/api/platform/v1/auth/login',
  '/api/platform/v1/auth/refresh',
].includes(path)

const tenantMenu = (state: ApiFixtureState) => {
  const unsafeName = '<img src=x onerror=window.__menuInjected=true>'
  const organizationChildren = [
    { key: 'core.members', type: 'page', name: '成员管理', route_name: 'tenant.members.list', route_path: '/untrusted/members', icon: null, children: [] },
    { key: 'core.departments', type: 'page', name: '部门管理', route_name: 'tenant.departments.list', route_path: '/untrusted/departments', icon: null, children: [] },
    { key: 'core.roles', type: 'page', name: '角色管理', route_name: 'tenant.roles.list', route_path: '/untrusted/roles', icon: null, children: [] },
  ]
  if (state.includeUnsafeMenu) {
    organizationChildren.push({ key: 'core.unsafe-label', type: 'page', name: unsafeName, route_name: 'tenant.account', route_path: '/untrusted/account', icon: null, children: [] })
  }
  const items = [
    {
      key: 'core.organization', type: 'group', name: '组织权限', route_name: null, icon: null,
      children: organizationChildren,
    },
    {
      key: 'core.system', type: 'group', name: '系统管理', route_name: null, icon: null,
      children: [
        { key: 'core.modules', type: 'page', name: '模块管理', route_name: 'tenant.modules.list', route_path: '/untrusted/modules', icon: null, children: [] },
        { key: 'core.audit', type: 'page', name: '审计日志', route_name: 'tenant.audit.list', route_path: '/untrusted/audit', icon: null, children: [] },
      ],
    },
    {
      key: 'examples', type: 'group', name: '通用示例', route_name: null, icon: null,
      children: [
        { key: 'example.targets', type: 'page', name: '示例目标', route_name: 'example-target-list', route_path: '/examples/targets', icon: null, children: [] },
        { key: 'example.references', type: 'page', name: '统一共享主档', route_name: 'example-reference-list', route_path: '/examples/references', icon: null, children: [] },
        { key: 'example.work-items', type: 'page', name: '示例工作项', route_name: 'example-work-item-list', route_path: '/examples/work-items', icon: null, children: [] },
        { key: 'example.policy', type: 'page', name: '目标策略发布', route_name: 'example-work-item-policy', route_path: '/examples/work-item-policies', icon: null, children: [] },
      ],
    },
  ]
  if (state.includeUnknownMenu) {
    items.push({
      key: 'unknown', type: 'group', name: '未知贡献', route_name: null, icon: null,
      children: [{ key: 'unknown.page', type: 'page', name: '不可信页面', route_name: 'server-injected-page', route_path: '/app/members', icon: null, children: [] }],
    })
  }
  return items
}

const platformMenu = () => [{
  key: 'platform.control', type: 'group', name: '平台治理', route_name: null, icon: null,
  children: [
    { key: 'platform.tenants', type: 'page', name: '租户管理', route_name: 'platform.tenants.list', route_path: '/untrusted/platform/tenants', icon: null, children: [] },
    { key: 'platform.operators', type: 'page', name: '平台操作员', route_name: 'platform.operators.list', route_path: '/untrusted/platform/operators', icon: null, children: [] },
    { key: 'platform.roles', type: 'page', name: '平台角色', route_name: 'platform.roles.list', route_path: '/untrusted/platform/roles', icon: null, children: [] },
    { key: 'platform.audit', type: 'page', name: '平台审计', route_name: 'platform.audit.list', route_path: '/untrusted/platform/audit', icon: null, children: [] },
  ],
}]

const candidateRows = (state: ApiFixtureState, targetType: string) => {
  if (state.targetMode === 'zero') return []
  const suffixes = state.targetMode === 'single' ? ['A'] : ['A', 'B', 'C']
  return suffixes.map((suffix, index) => ({
    target_resource_key: targetType,
    target_role: 'primary',
    target_id: String(9001 + index),
    label: `${targetType === 'example.queue' ? 'Queue' : 'Project'} ${suffix}`,
    status: 'active',
  }))
}

const handleApi = async (route: Route, state: ApiFixtureState): Promise<void> => {
  const request = route.request()
  const url = new URL(request.url())
  const path = url.pathname
  const method = request.method()
  const operation = `${method} ${path}`
  state.requestCounts.set(operation, (state.requestCounts.get(operation) ?? 0) + 1)
  const injectedProblem = state.nextProblems.get(operation)
  if (injectedProblem !== undefined) {
    state.nextProblems.delete(operation)
    await fulfillProblem(route, injectedProblem)
    return
  }

  if (path.startsWith('/api/platform/v1/') && !isPlatformPublic(path)
    && authorization(route) !== `Bearer ${state.platformToken}`) {
    await fulfillProblem(route, { status: 401, code: 'AUTH_AUDIENCE_MISMATCH', detail: 'Platform session required.' })
    return
  }
  if (path.startsWith('/api/v1/') && !isTenantPublic(path)
    && authorization(route) !== `Bearer ${state.tenantToken}`) {
    await fulfillProblem(route, { status: 401, code: 'AUTH_AUDIENCE_MISMATCH', detail: 'Tenant session required.' })
    return
  }
  if (path.startsWith('/api/v1/') && !isTenantPublic(path)
    && state.expireTenantAccess && authorization(route) === 'Bearer tenant-access-1') {
    await fulfillProblem(route, { status: 401, code: 'AUTH_SESSION_EXPIRED', detail: 'Session expired.' })
    return
  }

  if (operation === 'POST /api/v1/auth/login') {
    const body = request.postDataJSON() as { email?: string }
    state.tenantToken = 'tenant-access-1'
    state.expireTenantAccess = false
    if (body.email?.includes('multi')) {
      await fulfillJson(route, { data: {
        state: 'tenant_selection_required', challenge_token: 'tenant-challenge-token', expires_at: '2026-07-16T15:00:00.000Z',
        tenants: [
          { tenant_id: '101', tenant_code: 'alpha', tenant_name: 'Alpha Team', tenant_member_id: '501', member_display_name: 'Alice' },
          { tenant_id: '202', tenant_code: 'beta', tenant_name: 'Beta Team', tenant_member_id: '502', member_display_name: 'Alice' },
        ],
      } })
      return
    }
    await fulfillJson(route, { data: { state: 'authenticated', access_token: state.tenantToken } })
    return
  }
  if (operation === 'POST /api/v1/auth/tenants/select') {
    const body = request.postDataJSON() as { tenant_id?: string }
    state.activeTenantId = body.tenant_id ?? '101'
    state.tenantToken = `tenant-access-${state.activeTenantId}`
    await fulfillJson(route, { data: { state: 'authenticated', access_token: state.tenantToken } })
    return
  }
  if (operation === 'POST /api/v1/auth/tenant-switch/challenge') {
    await fulfillJson(route, { data: {
      state: 'tenant_selection_required', challenge_token: 'tenant-switch-token', expires_at: '2026-07-16T15:00:00.000Z',
      tenants: [{ tenant_id: state.activeTenantId === '101' ? '202' : '101', tenant_code: 'other', tenant_name: 'Other Team', tenant_member_id: '599', member_display_name: 'Alice' }],
    } })
    return
  }
  if (operation === 'POST /api/v1/auth/refresh') {
    state.refreshCount += 1
    await sleep(state.refreshDelayMs)
    state.tenantToken = 'tenant-access-2'
    state.expireTenantAccess = false
    await fulfillJson(route, { data: { state: 'authenticated', access_token: state.tenantToken } })
    return
  }
  if (operation === 'GET /api/v1/auth/context') {
    await fulfillJson(route, { data: {
      audience: 'tenant',
      account: { id: '12', display_name: 'Alice' },
      tenant: { id: state.activeTenantId, display_name: state.activeTenantId === '101' ? 'Alpha Team' : 'Beta Team' },
      member: { id: state.activeTenantId === '101' ? '501' : '502', display_name: 'Alice' },
      module_keys: state.tenantModules,
      permission_keys: state.tenantPermissions,
      authorization_revision: '18',
    } })
    return
  }
  if (operation === 'GET /api/v1/menus') {
    await fulfillJson(route, { data: tenantMenu(state), meta: { authorization_revision: '18' } })
    return
  }
  if (operation === 'POST /api/v1/auth/logout') {
    await fulfillJson(route, null, 204)
    return
  }

  if (operation === 'GET /api/v1/members') {
    const tenantAtRequest = state.activeTenantId
    if (state.memberDelayMs > 0) await sleep(state.memberDelayMs)
    await fulfillJson(route, { data: [{ id: '9007199254740993123', display_name: `Member ${tenantAtRequest}`, member_no: `M-${tenantAtRequest}`, status: 'active', role_keys: ['core.tenant-owner'] }], meta: { total: 1 } })
    return
  }
  if (method === 'GET' && /^\/api\/v1\/members\/[1-9][0-9]*\/effective-access$/.test(path)) {
    const memberId = path.split('/').at(-2) ?? '0'
    const page = Number.parseInt(url.searchParams.get('page') ?? '1', 10)
    const pageSize = Number.parseInt(url.searchParams.get('page_size') ?? '20', 10)
    const operations = state.effectiveAccessEmpty ? [] : page === 1 ? [{
      resource_key: 'example.authorization-resource-with-a-very-long-resource-key',
      module_key: 'example.work-item',
      operation: 'list',
      ownership: 'business_target_owned',
      access_mode: 'explicit_targets',
      target_cardinality: 'many_readable',
      permission_match: 'all',
      required_permission_keys: ['example.work-item.read'],
      functional_allowed: true,
      data_access: {
        mode: 'conditional',
        runtime_decision_required: true,
        group_match: 'any',
        groups: [{
          source_role_key: 'core.tenant-owner',
          condition_match: 'all',
          conditions: [{
            condition_key: 'core.specified_objects',
            target_resource_key: 'example.project',
            target_count: 2,
          }],
        }],
      },
    }] : [{
      resource_key: 'example.reference-item',
      module_key: 'example.reference',
      operation: 'use',
      ownership: 'shared_master',
      access_mode: 'global_reference_read',
      target_cardinality: 'none',
      permission_match: 'all',
      required_permission_keys: ['example.reference.use'],
      functional_allowed: true,
      data_access: {
        mode: 'global_reference_read',
        runtime_decision_required: false,
        group_match: 'any',
        groups: [],
      },
    }]
    await fulfillJson(route, {
      data: {
        preview_kind: 'authorization_inputs',
        evaluated_at: '2026-07-19T09:30:00.000Z',
        snapshot_revision: 'a'.repeat(64),
        member: {
          id: memberId,
          display_name: 'Member 101',
          status: state.effectiveAccessEmpty ? 'suspended' : 'active',
          primary_department_id: state.effectiveAccessEmpty ? null : '11',
          effective: !state.effectiveAccessEmpty,
        },
        roles: state.effectiveAccessEmpty
          ? []
          : [{ id: '21', key: 'core.tenant-owner', name: 'Tenant Owner', is_builtin: true }],
        permission_keys: state.effectiveAccessEmpty ? [] : [
          'core.member.effective-access.read',
          'example.work-item.authorization-preview-with-a-very-long-permission-key.read',
        ],
        resource_operations: operations,
      },
      meta: state.effectiveAccessEmpty
        ? { request_id: requestId, page: 1, page_size: pageSize, total: 0, total_pages: 0 }
        : { request_id: requestId, page, page_size: pageSize, total: 21, total_pages: 2 },
    })
    return
  }
  if (operation === 'GET /api/v1/departments') {
    await fulfillJson(route, { data: [{ id: '11', name: 'Operations', code: 'operations', parent_id: null, status: 'active' }] })
    return
  }
  if (operation === 'GET /api/v1/roles') {
    await fulfillJson(route, { data: [{ id: '21', name: 'Tenant Owner', key: 'core.tenant-owner', status: 'active', permission_count: 23 }] })
    return
  }
  if (operation === 'GET /api/v1/modules') {
    await fulfillJson(route, { data: state.tenantModules.map(moduleKey => ({ module_key: moduleKey, name: moduleKey, status: 'enabled', version: '0.1.0' })) })
    return
  }
  if (operation === 'GET /api/v1/audit-events') {
    await fulfillJson(route, { data: [{ id: '71', created_at: '2026-07-16T06:00:00.000Z', event_type: 'tenant.member.updated', actor_label: 'Alice', request_id: requestId }] })
    return
  }
  if (operation === 'GET /api/v1/authorization/target-candidates') {
    if (url.searchParams.get('mode') === 'policy-config' && state.policySelectionForbidden) {
      await fulfillProblem(route, { status: 403, code: 'AUTHZ_POLICY_SELECTION_DENIED', detail: 'Delegation permission required.' })
      return
    }
    const targetType = url.searchParams.get('target_resource_key') ?? 'example.project'
    const data = candidateRows(state, targetType)
    await fulfillJson(route, { data, meta: { available_count: data.length, total: data.length } })
    return
  }
  if (operation === 'GET /api/v1/example/work-items') {
    const targetIds = url.searchParams.getAll('target_id')
    await fulfillJson(route, { data: targetIds.map((targetId, index) => ({
      id: String(8001 + index), title: `Work item ${targetId}`, status: index % 2 === 0 ? 'open' : 'active', revision: '2',
      boundary_target: { target_resource_key: 'example.project', target_role: 'primary', target_id: targetId, label: `Project ${String.fromCharCode(65 + index)}` },
    })), meta: { total: targetIds.length, target_scope: { mode: targetIds.length > 1 ? 'multiple' : 'single' } } })
    return
  }
  if (operation === 'GET /api/v1/example/work-items/aggregate') {
    await fulfillJson(route, { data: { target_count: candidateRows(state, 'example.project').length, open_count: 2, active_count: 1 } })
    return
  }
  if (operation === 'POST /api/v1/example/work-items') {
    state.createCount += 1
    await fulfillJson(route, { data: { id: String(8100 + state.createCount), title: 'Created work item', status: 'open', revision: '1' } }, 201)
    return
  }
  if (operation === 'GET /api/v1/example/reference-items/candidates') {
    await fulfillJson(route, { data: [
      { id: '9007199254740993123', code: 'GLOBAL-01', name: 'Global Reference', owner_type: 'deployment', status: 'active' },
      { id: '9007199254740993124', code: 'TENANT-01', name: 'Tenant Reference', owner_type: 'tenant', status: 'active' },
    ], meta: { total: 2 } })
    return
  }
  if (operation === 'POST /api/v1/example/work-item-view-policies') {
    const body = request.postDataJSON() as { targets?: Array<{ target_ids?: string[] }> }
    const ids = body.targets?.[0]?.target_ids ?? []
    await fulfillJson(route, { data: ids.map(id => ({ target_label: `Project ${id}`, status: 'published', message: 'Applied' })) })
    return
  }

  if (operation === 'POST /api/platform/v1/auth/login') {
    state.platformToken = 'platform-access-1'
    await fulfillJson(route, { data: { state: 'authenticated', access_token: state.platformToken } })
    return
  }
  if (operation === 'POST /api/platform/v1/auth/refresh') {
    state.platformToken = 'platform-access-2'
    await fulfillJson(route, { data: { state: 'authenticated', access_token: state.platformToken } })
    return
  }
  if (operation === 'GET /api/platform/v1/auth/context') {
    await fulfillJson(route, { data: {
      audience: 'platform', account: { id: '12', display_name: 'Alice' }, operator: { id: '31', display_name: 'Platform Owner' },
      permission_keys: state.platformPermissions, authorization_revision: '7',
    } })
    return
  }
  if (operation === 'GET /api/platform/v1/menus') {
    await fulfillJson(route, { data: platformMenu(), meta: { authorization_revision: '7' } })
    return
  }
  if (operation === 'POST /api/platform/v1/auth/logout') {
    await fulfillJson(route, null, 204)
    return
  }
  if (operation === 'GET /api/platform/v1/tenants') {
    await fulfillJson(route, { data: [
      { id: '101', display_name: 'Alpha Team', code: 'alpha', status: 'active', created_at: '2026-07-01T00:00:00.000Z' },
      { id: '202', display_name: 'Beta Team', code: 'beta', status: 'suspended', created_at: '2026-07-02T00:00:00.000Z' },
    ] })
    return
  }
  if (method === 'GET' && /^\/api\/platform\/v1\/tenants\/\d+$/.test(path)) {
    const tenantId = path.split('/').at(-1) ?? '0'
    await fulfillJson(route, { data: { id: tenantId, display_name: tenantId === '101' ? 'Alpha Team' : 'Beta Team', code: tenantId === '101' ? 'alpha' : 'beta', status: 'active', timezone: 'Asia/Shanghai', locale: 'zh-CN', revision: '3' } })
    return
  }
  if (operation === 'GET /api/platform/v1/operators') {
    await fulfillJson(route, { data: [{ id: '31', display_name: 'Platform Owner', email: 'platform@example.test', status: 'active', role_keys: ['platform.bootstrap-owner'] }] })
    return
  }
  if (operation === 'GET /api/platform/v1/roles') {
    await fulfillJson(route, { data: [{ id: '41', name: 'Platform Owner', key: 'platform.bootstrap-owner', status: 'active', permission_count: 18 }] })
    return
  }
  if (operation === 'GET /api/platform/v1/audit-events') {
    await fulfillJson(route, { data: [{ id: '81', created_at: '2026-07-16T06:00:00.000Z', event_type: 'tenant.activated', operator_label: 'Platform Owner', target_tenant_id: '101' }] })
    return
  }

  await fulfillProblem(route, { status: 404, code: 'RESOURCE_NOT_FOUND', detail: `No fixture for ${operation}` })
}

export const installApiFixture = async (page: Page, state: ApiFixtureState): Promise<void> => {
  await page.route('**/api/**', route => handleApi(route, state))
}

export const loginTenant = async (page: Page, email = 'single@example.test'): Promise<void> => {
  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill('correct-password')
  await page.getByRole('button', { name: '登录' }).click()
  if (email.includes('multi')) {
    await expect(page).toHaveURL(/\/select-tenant/)
    await page.getByRole('button', { name: '进入工作区' }).click()
  }
  await expect(page).toHaveURL(/\/app$/)
  await expect(page.locator('.pa-shell-breadcrumb').getByText('租户工作区', { exact: true })).toBeVisible()
}

export const loginPlatform = async (page: Page): Promise<void> => {
  await page.goto('/platform/login')
  await page.getByLabel('邮箱').fill('platform@example.test')
  await page.getByLabel('密码').fill('correct-password')
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(/\/platform$/)
  await expect(page.locator('.pa-shell-breadcrumb').getByText('平台控制面', { exact: true })).toBeVisible()
}

export const openNavigationIfNeeded = async (page: Page): Promise<void> => {
  if (await page.locator('.mobile-navigation-drawer').isVisible()) return
  const trigger = page.getByRole('button', { name: '打开导航' })
  if (await trigger.isVisible()) {
    await trigger.click()
    const drawer = page.locator('.mobile-navigation-drawer')
    await expect(drawer).toBeVisible()
    const narrowLabels = await drawer.locator('.navigation-link > span:last-child').evaluateAll(elements => (
      elements.filter(element => element.getBoundingClientRect().width < 80).length
    ))
    expect(narrowLabels).toBe(0)
  }
}

export const navigateByLink = async (page: Page, name: string): Promise<void> => {
  await openNavigationIfNeeded(page)
  const drawer = page.locator('.mobile-navigation-drawer')
  const link = await drawer.isVisible()
    ? drawer.getByRole('link', { name })
    : page.locator('.pa-shell-sidebar').getByRole('link', { name })
  await link.click()
  await expect(drawer).toBeHidden()
}

export const monitorPageErrors = (page: Page): string[] => {
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
      errors.push(message.text())
    }
  })
  page.on('response', response => {
    if (response.status() >= 400 && !new URL(response.url()).pathname.startsWith('/api/')) {
      errors.push(`Unexpected ${response.status()} response: ${response.url()}`)
    }
  })
  return errors
}

export const expectNoViewportOverflow = async (page: Page): Promise<void> => {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
    outside: [...document.querySelectorAll('button, a, input, .el-table')].filter(element => {
      const rectangle = element.getBoundingClientRect()
      return rectangle.left < -1 || rectangle.right > window.innerWidth + 1
    }).length,
    narrowNavigationLabels: [...document.querySelectorAll('.navigation-link > span:last-child')]
      .filter(element => element.getClientRects().length > 0
        && element.getBoundingClientRect().width < 80
        && element.textContent !== null
        && element.textContent.length >= 4)
      .length,
  }))
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1)
  expect(dimensions.outside).toBe(0)
  expect(dimensions.narrowNavigationLabels).toBe(0)
}

export const captureAcceptanceScreenshot = async (
  page: Page,
  testInfo: TestInfo,
  name: string,
): Promise<void> => {
  await page.screenshot({ path: testInfo.outputPath(`${name}.png`), fullPage: true })
}
