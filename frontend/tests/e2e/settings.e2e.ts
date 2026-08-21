import { expect, test } from '@playwright/test'
import type { Page, Response } from '@playwright/test'

import {
  browserPassword,
  captureFullStackScreenshot,
  monitorFullStackErrors,
} from '../fixtures/full-stack'

const email = 'browser-owner@example.test'
const moduleKey = 'example.target'
const settingKey = 'display-density'
const settingPath = `/api/v1/settings/${moduleKey}/${settingKey}`
const strongEtagPattern = /^"[^"\r\n]+"$/

interface SettingItem {
  module_key: string
  setting_key: string
  configured: boolean
  source_scope: string | null
  value?: unknown
  revision: string
  etag: string | null
}

const loginAndEnterTenant = async (page: Page, tenantName: string): Promise<void> => {
  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(browserPassword())
  const loginResponsePromise = page.waitForResponse(response => (
    response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/api/v1/auth/login'
  ))
  await page.getByRole('button', { name: '登录' }).click()
  expect((await loginResponsePromise).status()).toBe(200)

  await expect(page).toHaveURL(url => url.pathname === '/select-tenant')
  await page.getByText(tenantName, { exact: true }).click()
  const tenantResponsePromise = page.waitForResponse(response => (
    response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/api/v1/auth/tenants/select'
  ))
  await page.getByRole('button', { name: '进入工作区' }).click()
  expect((await tenantResponsePromise).status()).toBe(200)
  await expect(page).toHaveURL(url => url.pathname === '/app')
  await expect(page.locator('.workspace-summary').getByText(tenantName, { exact: true })).toBeVisible()
}

const waitForSettingsResponse = (page: Page, method: 'GET' | 'PUT' | 'DELETE'): Promise<Response> => (
  page.waitForResponse(response => (
    response.request().method() === method
    && new URL(response.url()).pathname === (method === 'GET' ? '/api/v1/settings' : settingPath)
  ))
)

const listItem = async (response: Response): Promise<SettingItem> => {
  const body = await response.json() as { data: { items: SettingItem[] } }
  const item = body.data.items.find(candidate => (
    candidate.module_key === moduleKey && candidate.setting_key === settingKey
  ))
  if (item === undefined) throw new Error('Expected the display-density setting in the list response.')

  return item
}

const responseItem = async (response: Response): Promise<SettingItem> => {
  const body = await response.json() as { data: SettingItem }

  return body.data
}

const strongResponseEtag = (response: Response): string => {
  const etag = response.headers().etag
  expect(etag).toMatch(strongEtagPattern)
  if (etag === undefined) throw new Error('Expected a strong ETag response header.')

  return etag
}

const expectPositiveRevision = (revision: string): void => {
  expect(revision).toMatch(/^[1-9][0-9]*$/)
}

const expectNoHorizontalOverflow = async (page: Page): Promise<void> => {
  const dimensions = await page.evaluate(() => ({
    body: document.body.scrollWidth,
    document: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }))
  expect(Math.max(dimensions.body, dimensions.document)).toBeLessThanOrEqual(dimensions.viewport + 1)
}

test('real tenant settings create, reload, update, and unset with strong ETags', async ({ page }, testInfo) => {
  const errors = monitorFullStackErrors(page)
  const tenantName = testInfo.project.name.includes('mobile') ? 'Beta Team' : 'Alpha Team'
  await loginAndEnterTenant(page, tenantName)

  const initialListPromise = waitForSettingsResponse(page, 'GET')
  await page.goto('/app/settings')
  const initialList = await initialListPromise
  expect(initialList.status()).toBe(200)
  strongResponseEtag(initialList)

  const initial = await listItem(initialList)
  expect(initial).toMatchObject({
    configured: false,
    etag: null,
    source_scope: 'default',
    value: 'comfortable',
  })
  expectPositiveRevision(initial.revision)

  const setting = page.locator(`[data-setting-key="${moduleKey}/${settingKey}"]`)
  const editor = setting.getByLabel('Display density', { exact: true })
  const save = setting.getByRole('button', { name: `Save Display density (${moduleKey}/${settingKey})` })
  const unset = setting.getByRole('button', { name: `Unset Display density (${moduleKey}/${settingKey})` })
  await expect(setting.getByRole('heading', { name: 'Display density' })).toBeVisible()
  await expect(editor).toHaveValue('1')
  await expect(unset).toBeDisabled()
  await expectNoHorizontalOverflow(page)

  await editor.selectOption({ label: 'compact' })
  const createResponsePromise = waitForSettingsResponse(page, 'PUT')
  await save.click()
  const createResponse = await createResponsePromise
  expect(createResponse.status()).toBe(200)
  expect(createResponse.request().headers()['if-none-match']).toBe('*')
  expect(createResponse.request().headers()['if-match']).toBeUndefined()
  expect(createResponse.request().headers()['idempotency-key']).toMatch(/^idem_[a-zA-Z0-9]+$/)
  const createEtag = strongResponseEtag(createResponse)
  const created = await responseItem(createResponse)
  expect(created).toMatchObject({
    configured: true,
    etag: createEtag,
    source_scope: 'tenant',
    value: 'compact',
  })
  expectPositiveRevision(created.revision)
  await expect(editor).toHaveValue('0')
  await expect(unset).toBeEnabled()

  const reloadResponsePromise = waitForSettingsResponse(page, 'GET')
  await page.getByRole('button', { name: 'Reload settings' }).click()
  const reloadResponse = await reloadResponsePromise
  expect(reloadResponse.status()).toBe(200)
  strongResponseEtag(reloadResponse)
  const reloaded = await listItem(reloadResponse)
  expect(reloaded).toMatchObject({
    configured: true,
    etag: createEtag,
    source_scope: 'tenant',
    value: 'compact',
  })
  await expect(editor).toHaveValue('0')

  await editor.selectOption({ label: 'comfortable' })
  const updateResponsePromise = waitForSettingsResponse(page, 'PUT')
  await save.click()
  const updateResponse = await updateResponsePromise
  expect(updateResponse.status()).toBe(200)
  expect(updateResponse.request().headers()['if-match']).toBe(createEtag)
  expect(updateResponse.request().headers()['if-none-match']).toBeUndefined()
  const updateEtag = strongResponseEtag(updateResponse)
  expect(updateEtag).not.toBe(createEtag)
  const updated = await responseItem(updateResponse)
  expect(updated).toMatchObject({
    configured: true,
    etag: updateEtag,
    source_scope: 'tenant',
    value: 'comfortable',
  })
  expectPositiveRevision(updated.revision)

  const unsetResponsePromise = waitForSettingsResponse(page, 'DELETE')
  await unset.click()
  const unsetResponse = await unsetResponsePromise
  expect(unsetResponse.status()).toBe(200)
  expect(unsetResponse.request().headers()['if-match']).toBe(updateEtag)
  expect(unsetResponse.request().headers()['idempotency-key']).toMatch(/^idem_[a-zA-Z0-9]+$/)
  const unsetEtag = strongResponseEtag(unsetResponse)
  expect(unsetEtag).not.toBe(updateEtag)
  const unsetItem = await responseItem(unsetResponse)
  expect(unsetItem).toMatchObject({ configured: false, etag: unsetEtag })
  expectPositiveRevision(unsetItem.revision)

  const finalListPromise = waitForSettingsResponse(page, 'GET')
  await page.getByRole('button', { name: 'Reload settings' }).click()
  const finalList = await finalListPromise
  expect(finalList.status()).toBe(200)
  strongResponseEtag(finalList)
  const final = await listItem(finalList)
  expect(final).toMatchObject({
    configured: false,
    etag: unsetEtag,
    source_scope: 'default',
    value: 'comfortable',
  })
  expectPositiveRevision(final.revision)
  await expect(editor).toHaveValue('1')
  await expect(unset).toBeEnabled()
  await expectNoHorizontalOverflow(page)
  await captureFullStackScreenshot(page, testInfo, `real-settings-${tenantName === 'Alpha Team' ? 'alpha' : 'beta'}`)
  expect(errors).toEqual([])
})
