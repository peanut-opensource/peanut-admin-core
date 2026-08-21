import { expect, test } from '@playwright/test'

import {
  captureAcceptanceScreenshot,
  createApiFixtureState,
  expectNoViewportOverflow,
  installApiFixture,
  loginPlatform,
  loginTenant,
  monitorPageErrors,
  navigateByLink,
  openNavigationIfNeeded,
} from '../fixtures/api'

test('platform workspace manages target tenants without tenant audience', async ({ page }, testInfo) => {
  const state = createApiFixtureState()
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginPlatform(page)

  await navigateByLink(page, '租户管理')
  await expect(page.getByText('Alpha Team').last()).toBeVisible()
  await page.getByRole('link', { name: '查看' }).first().click()
  await expect(page).toHaveURL(/\/platform\/tenants\/101$/)
  await expect(page.getByText('Alpha Team').last()).toBeVisible()

  const tenantStatus = await page.evaluate(async token => (await fetch('/api/v1/example/reference-items/candidates', {
    headers: { Authorization: `Bearer ${token}` },
  })).status, state.platformToken)
  expect(tenantStatus).toBe(401)

  await expectNoViewportOverflow(page)
  await captureAcceptanceScreenshot(page, testInfo, 'platform-tenant-detail')
  expect(errors).toEqual([])
})

test('concurrent 401 responses use one refresh rotation', async ({ page }) => {
  const state = createApiFixtureState({ refreshDelayMs: 750 })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)
  state.expireTenantAccess = true

  await navigateByLink(page, '成员管理')
  await navigateByLink(page, '角色管理')
  await expect(page).toHaveURL(/\/app\/roles$/)
  await expect(page.getByText('Tenant Owner')).toBeVisible()
  expect(state.refreshCount).toBe(1)
  expect(errors).toEqual([])
})

test('tenant switch prevents a late old-tenant response from appearing', async ({ page }) => {
  const state = createApiFixtureState({ memberDelayMs: 500 })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await navigateByLink(page, '成员管理')
  await openNavigationIfNeeded(page)
  await page.getByRole('button', { name: '切换租户' }).click()
  await expect(page).toHaveURL(/\/select-tenant/)
  await page.getByRole('button', { name: '进入工作区' }).click()
  await expect(page).toHaveURL(/\/app$/)
  await expect(page.locator('.workspace-summary').getByText('Beta Team')).toBeVisible()
  await page.waitForTimeout(600)
  await expect(page.getByText('Member 101')).toHaveCount(0)
  expect(errors).toEqual([])
})

test('429 and 503 remain explicit states with request correlation', async ({ page }) => {
  const state = createApiFixtureState()
  const errors = monitorPageErrors(page)
  state.nextProblems.set('GET /api/v1/members', {
    status: 429,
    code: 'RATE_LIMITED',
    detail: 'Too many requests. Retry later.',
    retryAfter: '3',
  })
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/members')
  await expect(page.getByText('Too many requests. Retry later.')).toBeVisible()
  await expect(page.getByText('req_browser_fixture')).toBeVisible()
  await expect(page.getByRole('button', { name: /秒后重试/ })).toBeDisabled()

  state.tenantModules = state.tenantModules.filter(moduleKey => moduleKey !== 'example.work-item')
  await page.reload()
  await page.goto('/app/examples/work-items')
  await expect(page).toHaveURL(/service-unavailable/)
  await expect(page.getByText('This module is currently unavailable.')).toBeVisible()
  expect(errors).toEqual([])
})

test('404 hides existence and 412 requires an explicit reload', async ({ page }) => {
  const state = createApiFixtureState({ targetMode: 'single' })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginPlatform(page)

  state.nextProblems.set('GET /api/platform/v1/tenants/999', {
    status: 404,
    code: 'AUTHZ_DATA_DENIED',
    detail: 'The requested resource does not exist or is not accessible.',
  })
  await page.goto('/platform/tenants/999')
  await expect(page.getByText('租户不可用')).toBeVisible()
  await expect(page.getByText('未找到可显示的租户信息。')).toBeVisible()
  await expect(page.getByText(/permission|权限/i)).toHaveCount(0)

  await loginTenant(page)
  await page.goto('/app/examples/work-items')
  await page.getByRole('button', { name: '新建工作项' }).click()
  const dialog = page.getByRole('dialog', { name: '新建工作项' })
  await dialog.getByLabel('标题').fill('Concurrent edit')
  await dialog.getByRole('combobox').click()
  await page.getByRole('option', { name: 'Global Reference' }).click()
  state.nextProblems.set('POST /api/v1/example/work-items', {
    status: 412,
    code: 'REVISION_MISMATCH',
    detail: 'Data changed. Reload before saving.',
  })
  await dialog.getByRole('button', { name: '创建' }).click()
  await expect(dialog.getByText('Data changed. Reload before saving.')).toBeVisible()
  await expect(dialog.getByRole('button', { name: '重新加载' })).toBeVisible()
  expect(state.createCount).toBe(0)
  expect(errors).toEqual([])
})
