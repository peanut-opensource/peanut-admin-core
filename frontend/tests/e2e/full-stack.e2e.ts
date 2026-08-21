import { randomUUID } from 'node:crypto'

import { expect, test } from '@playwright/test'
import type { Page, Response } from '@playwright/test'

import {
  browserPassword,
  captureFullStackScreenshot,
  expectApiResponse,
  monitorFullStackErrors,
  observeApi,
} from '../fixtures/full-stack'

const email = 'browser-owner@example.test'

const login = async (page: Page, password: string): Promise<Response> => {
  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(password)
  const responsePromise = page.waitForResponse(response => (
    response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/api/v1/auth/login'
  ))
  await page.getByRole('button', { name: '登录' }).click()

  return responsePromise
}

const enterAlphaTeam = async (page: Page): Promise<void> => {
  await expect(page).toHaveURL(/\/select-tenant/)
  await page.getByText('Alpha Team', { exact: true }).click()
  await page.getByRole('button', { name: '进入工作区' }).click()
  await expect(page).toHaveURL(url => url.pathname === '/app')
  await expect(page.locator('.workspace-summary').getByText('Alpha Team')).toBeVisible()
}

const submitPasswordChange = async (
  page: Page,
  currentPassword: string,
  newPassword: string,
): Promise<Response> => {
  await page.goto('/app/account')
  await expect(page.getByText('b***@example.test')).toBeVisible()
  await page.getByTestId('current-password').fill(currentPassword)
  await page.getByTestId('new-password').fill(newPassword)
  await page.getByTestId('confirm-password').fill(newPassword)
  const responsePromise = page.waitForResponse(response => (
    response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/api/v1/account/password'
  ))
  await page.getByRole('button', { name: '修改密码' }).click()

  return responsePromise
}

test('real tenant login reaches multi-target read and single-target write', async ({ page }, testInfo) => {
  const responses = observeApi(page)
  const errors = monitorFullStackErrors(page)

  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(browserPassword())
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(/\/select-tenant/)
  await expect(page.getByText('Alpha Team')).toBeVisible()
  await expect(page.getByText('Beta Team')).toBeVisible()
  await page.getByRole('button', { name: '进入工作区' }).click()
  await expect(page).toHaveURL(url => url.pathname === '/app')
  await expect(page.locator('.workspace-summary').getByText('Alpha Team')).toBeVisible()

  await page.goto('/app/examples/work-items')
  await expect(page.getByText('0 of 2 targets selected')).toBeVisible()
  await page.getByRole('button', { name: '选择全部已授权' }).click()
  await expect(page.getByText('2 of 2 targets selected')).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '归属目标' })).toBeVisible()
  await expect(page.getByText('Project A work')).toBeVisible()
  await expect(page.getByText('Project B work')).toBeVisible()

  const selector = page.locator('.pa-target-selector')
  await selector.locator('.el-tag').filter({ hasText: 'Project B' })
    .getByRole('button', { name: 'Close this tag' }).click()
  await expect(page.getByText('1 of 2 targets selected')).toBeVisible()
  await expect(page.getByRole('button', { name: '新建工作项' })).toBeEnabled()
  await page.getByRole('button', { name: '新建工作项' }).click()
  const dialog = page.getByRole('dialog', { name: '新建工作项' })
  await dialog.getByLabel('标题').fill(`Full-stack ${testInfo.project.name}`)
  await dialog.getByRole('combobox').click()
  await page.getByRole('option', { name: 'Public Reference' }).click()
  await dialog.getByRole('button', { name: '创建' }).click()
  await expect(dialog).toBeHidden()
  await expect(page.getByText(`Full-stack ${testInfo.project.name}`)).toBeVisible()

  const storage = await page.evaluate(() => ({
    local: Object.entries(localStorage),
    session: Object.entries(sessionStorage),
  }))
  expect(JSON.stringify(storage)).not.toContain('access_token')
  expect(JSON.stringify(storage)).not.toContain('refresh_token')
  await captureFullStackScreenshot(page, testInfo, 'real-tenant-write')

  expectApiResponse(responses, 'POST', '/api/v1/auth/login', 200)
  expectApiResponse(responses, 'POST', '/api/v1/auth/tenants/select', 200)
  expectApiResponse(responses, 'GET', '/api/v1/auth/context', 200)
  expectApiResponse(responses, 'GET', '/api/v1/authorization/target-candidates', 200)
  expectApiResponse(responses, 'GET', '/api/v1/example/work-items', 200)
  expectApiResponse(responses, 'GET', '/api/v1/example/reference-items/candidates', 200)
  expectApiResponse(responses, 'POST', '/api/v1/example/work-items', 201)
  expect(errors).toEqual([])
})

test('real platform login reaches the protected tenant collection', async ({ page }, testInfo) => {
  const responses = observeApi(page)
  const errors = monitorFullStackErrors(page)

  await page.goto('/platform/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(browserPassword())
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(/\/platform$/)
  await page.goto('/platform/tenants')
  await expect(page.getByText('Alpha Team').last()).toBeVisible()
  await expect(page.getByText('Beta Team').last()).toBeVisible()
  await captureFullStackScreenshot(page, testInfo, 'real-platform-tenants')

  expectApiResponse(responses, 'POST', '/api/platform/v1/auth/login', 200)
  expectApiResponse(responses, 'GET', '/api/platform/v1/auth/context', 200)
  expectApiResponse(responses, 'GET', '/api/platform/v1/menus', 200)
  expectApiResponse(responses, 'GET', '/api/platform/v1/tenants', 200)
  expect(errors).toEqual([])
})

test('real tenant member effective access preview is authoritative and responsive', async ({ page }, testInfo) => {
  const errors = monitorFullStackErrors(page)
  const initialLogin = await login(page, browserPassword())
  expect(initialLogin.status()).toBe(200)
  await enterAlphaTeam(page)

  await page.goto('/app/members')
  const previewResponsePromise = page.waitForResponse(response => (
    response.request().method() === 'GET'
    && /^\/api\/v1\/members\/[1-9][0-9]*\/effective-access$/.test(new URL(response.url()).pathname)
  ))
  await page.getByRole('link', { name: '有效访问' }).click()
  const previewResponse = await previewResponsePromise

  expect(previewResponse.status()).toBe(200)
  expect(previewResponse.headers()['x-request-id']).toMatch(/^req_[a-zA-Z0-9_-]+$/)
  await expect(page).toHaveURL(/\/app\/members\/[1-9][0-9]*\/effective-access$/)
  await expect(page.locator('.pa-page-header__title').getByText('有效访问预览', { exact: true })).toBeVisible()
  await expect(page.getByText('core.member.effective-access.read')).toBeVisible()

  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }))
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1)
  await captureFullStackScreenshot(page, testInfo, 'real-member-effective-access')
  expect(errors).toEqual([])
})

test('real tenant account profile loads and saves through the protected API', async ({ page }, testInfo) => {
  const responses = observeApi(page)
  const errors = monitorFullStackErrors(page)

  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(browserPassword())
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(/\/select-tenant/)
  await page.getByRole('button', { name: '进入工作区' }).click()
  await expect(page).toHaveURL(url => url.pathname === '/app')
  await expect(page.locator('.workspace-summary').getByText('Alpha Team')).toBeVisible()

  await page.goto('/app/account')
  await expect(page.getByText('b***@example.test')).toBeVisible()
  const displayName = `Browser Owner ${testInfo.project.name}`
  await page.getByTestId('profile-display-name').fill(displayName)
  await page.getByTestId('profile-avatar-uri').fill('')
  await page.getByRole('button', { name: '保存资料' }).click()
  await expect(page.getByText('个人资料已保存。')).toBeVisible()
  await expect(page.getByText(displayName).first()).toBeVisible()
  await captureFullStackScreenshot(page, testInfo, 'real-account-profile')

  expectApiResponse(responses, 'GET', '/api/v1/account', 200)
  expectApiResponse(responses, 'PATCH', '/api/v1/account', 200)
  expect(errors).toEqual([])
})

test('real tenant account password change revokes the old password and remains reversible', async ({ page }, testInfo) => {
  const responses = observeApi(page)
  const errors = monitorFullStackErrors(page)
  const originalPassword = browserPassword()
  const replacementPassword = `Peanut-${testInfo.project.name}-${randomUUID()}!`
  let replacementIsActive = false

  try {
    const initialLogin = await login(page, originalPassword)
    expect(initialLogin.status()).toBe(200)
    await enterAlphaTeam(page)

    const changed = await submitPasswordChange(page, originalPassword, replacementPassword)
    expect(changed.status()).toBe(204)
    replacementIsActive = true
    await expect(page).toHaveURL(/\/login$/)

    const rejectedOldLogin = await login(page, originalPassword)
    expect(rejectedOldLogin.status()).toBe(401)
    await expect(page).toHaveURL(/\/login$/)

    const replacementLogin = await login(page, replacementPassword)
    expect(replacementLogin.status()).toBe(200)
    await enterAlphaTeam(page)

    const restored = await submitPasswordChange(page, replacementPassword, originalPassword)
    expect(restored.status()).toBe(204)
    replacementIsActive = false
    await expect(page).toHaveURL(/\/login$/)

    const restoredLogin = await login(page, originalPassword)
    expect(restoredLogin.status()).toBe(200)
    await expect(page).toHaveURL(/\/select-tenant/)

    expectApiResponse(responses, 'POST', '/api/v1/account/password', 204)
    expectApiResponse(responses, 'POST', '/api/v1/auth/login', 401)
    expect(errors).toEqual([])
  } finally {
    if (replacementIsActive) {
      const replacementLogin = await login(page, replacementPassword)
      expect(replacementLogin.status()).toBe(200)
      await enterAlphaTeam(page)
      const restored = await submitPasswordChange(page, replacementPassword, originalPassword)
      expect(restored.status()).toBe(204)
      replacementIsActive = false
      await expect(page).toHaveURL(/\/login$/)
    }
  }
})
