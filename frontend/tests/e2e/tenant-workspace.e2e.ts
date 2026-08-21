import { expect, test } from '@playwright/test'

import {
  captureAcceptanceScreenshot,
  createApiFixtureState,
  expectNoViewportOverflow,
  installApiFixture,
  loginTenant,
  monitorPageErrors,
  navigateByLink,
  openNavigationIfNeeded,
} from '../fixtures/api'

test('tenant selection, trusted menus, and audience isolation', async ({ page }, testInfo) => {
  const state = createApiFixtureState({ includeUnknownMenu: true, includeUnsafeMenu: true })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page, 'multi@example.test')

  await expect(page.locator('.workspace-summary').getByText('Alpha Team')).toBeVisible()
  await openNavigationIfNeeded(page)
  const navigationRoot = await page.locator('.mobile-navigation-drawer').isVisible()
    ? page.locator('.mobile-navigation-drawer')
    : page.locator('.pa-shell-sidebar')
  await expect(page.getByText('不可信页面')).toHaveCount(0)
  await expect(navigationRoot.getByText('<img src=x onerror=window.__menuInjected=true>', { exact: true })).toBeVisible()
  expect(await page.evaluate(() => (window as Window & { __menuInjected?: boolean }).__menuInjected)).not.toBe(true)

  const storage = await page.evaluate(() => ({
    local: Object.entries(localStorage),
    session: Object.entries(sessionStorage),
    cookies: document.cookie,
  }))
  expect(JSON.stringify(storage)).not.toContain('tenant-access')
  expect(JSON.stringify(storage)).not.toContain('permission_keys')

  await navigateByLink(page, '成员管理')
  await expect(page).toHaveURL(/\/app\/members$/)
  await expect(page.getByText('Member 101')).toBeVisible()
  await expect(page.getByText('core.tenant-owner')).toBeVisible()

  const platformStatus = await page.evaluate(async token => (await fetch('/api/platform/v1/tenants', {
    headers: { Authorization: `Bearer ${token}` },
  })).status, state.tenantToken)
  expect(platformStatus).toBe(401)

  await expectNoViewportOverflow(page)
  await captureAcceptanceScreenshot(page, testInfo, 'tenant-members')
  expect(errors).toEqual([])
})

test('manual unauthorized route does not load protected collection', async ({ page }) => {
  const state = createApiFixtureState({
    tenantPermissions: tenantPermissionsWithout('core.member.read'),
  })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/members')
  await expect(page).toHaveURL(/\/403$/)
  await expect(page.getByText('Access denied')).toBeVisible()
  expect(state.requestCounts.get('GET /api/v1/members') ?? 0).toBe(0)
  expect(errors).toEqual([])
})

test('member row opens the responsive effective access preview', async ({ page }, testInfo) => {
  const state = createApiFixtureState()
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/members')
  await page.getByRole('link', { name: '有效访问' }).click()
  await expect(page).toHaveURL(/\/app\/members\/9007199254740993123\/effective-access$/)
  await expect(page.getByText('Member 101')).toBeVisible()
  await expect(page.getByText('core.member.effective-access.read')).toBeVisible()
  await expect(page.getByText('example.authorization-resource-with-a-very-long-resource-key')).toBeVisible()
  await expect(page.getByText('仍需运行时判定')).toBeVisible()
  await expectNoViewportOverflow(page)
  await captureAcceptanceScreenshot(page, testInfo, 'member-effective-access')

  const endpoint = 'GET /api/v1/members/9007199254740993123/effective-access'
  const refresh = page.getByRole('toolbar', { name: '预览操作' }).getByRole('button', { name: '刷新' })
  await page.locator('.el-pagination .btn-next').click()
  await expect(page.getByText('example.reference-item')).toBeVisible()
  await refresh.click()
  await expect(page.getByText('example.reference-item')).toBeVisible()

  state.effectiveAccessEmpty = true
  await refresh.click()
  await expect(page.getByText('暂无可预览的资源操作')).toBeVisible()

  state.effectiveAccessEmpty = false
  state.nextProblems.set(endpoint, {
    status: 500,
    code: 'INTERNAL_ERROR',
    detail: 'Preview unavailable.',
  })
  await refresh.click()
  await expect(page.getByText('有效访问暂不可用')).toBeVisible()

  state.nextProblems.set(endpoint, {
    status: 403,
    code: 'AUTHZ_PERMISSION_DENIED',
    detail: 'Preview permission denied.',
  })
  await refresh.click()
  await expect(page.getByText('无权查看')).toBeVisible()

  state.nextProblems.set(endpoint, {
    status: 404,
    code: 'RESOURCE_NOT_FOUND',
    detail: 'Member not found.',
  })
  await refresh.click()
  await expect(page.getByText('成员不可用')).toBeVisible()

  await expectNoViewportOverflow(page)
  expect(state.requestCounts.get(endpoint)).toBe(7)
  expect(errors).toEqual([])
})

test('effective access permission hides the row action and blocks direct navigation before fetch', async ({ page }) => {
  const state = createApiFixtureState({
    tenantPermissions: tenantPermissionsWithout('core.member.effective-access.read'),
  })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/members')
  await expect(page.getByRole('link', { name: '有效访问' })).toHaveCount(0)
  await page.goto('/app/members/9007199254740993123/effective-access')
  await expect(page).toHaveURL(/\/403$/)
  expect(state.requestCounts.get('GET /api/v1/members/9007199254740993123/effective-access') ?? 0).toBe(0)
  expect(errors).toEqual([])
})

const tenantPermissionsWithout = (permission: string): string[] => [
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
].filter(item => item !== permission)
