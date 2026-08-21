import { expect, test } from '@playwright/test'
import type { Page, Response } from '@playwright/test'

import { browserPassword, monitorFullStackErrors } from '../fixtures/full-stack'

const email = 'browser-owner@example.test'

const loginAndEnterTenant = async (page: Page, tenantName: string): Promise<void> => {
  await page.goto('/login')
  await page.getByLabel('邮箱').fill(email)
  await page.getByLabel('密码').fill(browserPassword())
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(url => url.pathname === '/select-tenant')
  await page.getByText(tenantName, { exact: true }).click()
  await page.getByRole('button', { name: '进入工作区' }).click()
  await expect(page).toHaveURL(url => url.pathname === '/app')
}

const referenceResponse = (page: Page, method: string): Promise<Response> => page.waitForResponse(response => {
  const path = new URL(response.url()).pathname
  return response.request().method() === method && path.startsWith('/api/v1/reference-code-sets')
})

const codeListResponse = (page: Page): Promise<Response> => page.waitForResponse(response => (
  response.request().method() === 'GET'
  && /^\/api\/v1\/reference-code-sets\/[^/]+\/[^/]+\/codes$/.test(new URL(response.url()).pathname)
))

const detailResponse = (page: Page, code: string): Promise<Response> => page.waitForResponse(response => (
  response.request().method() === 'GET'
  && new URL(response.url()).pathname.endsWith(`/codes/${encodeURIComponent(code)}`)
))

const expectNoHorizontalOverflow = async (page: Page): Promise<void> => {
  const dimensions = await page.evaluate(() => ({
    body: document.body.scrollWidth,
    document: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }))
  expect(Math.max(dimensions.body, dimensions.document)).toBeLessThanOrEqual(dimensions.viewport + 1)
}

const observeLoadingState = async (page: Page): Promise<void> => {
  await page.evaluate(() => {
    const root = document.documentElement
    root.dataset.referenceLoadingObserved = document.querySelector('[data-reference-codes-state="loading"]') === null
      ? 'false'
      : 'true'
    const observer = new MutationObserver(() => {
      if (document.querySelector('[data-reference-codes-state="loading"]') !== null) {
        root.dataset.referenceLoadingObserved = 'true'
      }
    })
    observer.observe(document.body, { childList: true, subtree: true })
    globalThis.setTimeout(() => observer.disconnect(), 10_000)
  })
}

const expectLoadingObserved = async (page: Page): Promise<void> => {
  expect(await page.evaluate(() => document.documentElement.dataset.referenceLoadingObserved)).toBe('true')
}

const expectSingleUsableDialog = async (page: Page, name: string): Promise<void> => {
  const dialog = page.getByRole('dialog', { name })
  await expect(dialog).toBeVisible()
  const geometry = await page.locator('[role="dialog"]:visible').evaluateAll(dialogs => {
    const viewport = { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight }
    const visibleControls = (dialogElement: Element) => [...dialogElement.querySelectorAll('button, input, select, textarea')]
      .filter(element => {
        const rectangle = element.getBoundingClientRect()
        return rectangle.width > 0
          && rectangle.height > 0
          && !(element instanceof HTMLInputElement && element.closest('.el-input-number') !== null)
      })
      .map(element => {
        const rectangle = element.getBoundingClientRect()
        return { left: rectangle.left, right: rectangle.right, top: rectangle.top, bottom: rectangle.bottom }
      })
    const dialogRectangles = dialogs.map(dialogElement => {
      const rectangle = dialogElement.getBoundingClientRect()
      return {
        left: rectangle.left,
        right: rectangle.right,
        top: rectangle.top,
        bottom: rectangle.bottom,
        controls: visibleControls(dialogElement),
      }
    })
    const controlsOverlap = dialogRectangles.some(dialogRectangle => dialogRectangle.controls.some((left, index) => (
      dialogRectangle.controls.slice(index + 1).some(right => (
        Math.min(left.right, right.right) - Math.max(left.left, right.left) > 1
        && Math.min(left.bottom, right.bottom) - Math.max(left.top, right.top) > 1
      ))
    )))
    return {
      count: dialogRectangles.length,
      insideViewport: dialogRectangles.every(rectangle => (
        rectangle.left >= 0
        && rectangle.top >= 0
        && rectangle.right <= viewport.width + 1
        && rectangle.bottom <= viewport.height + 1
        && rectangle.controls.every(control => (
          control.left >= rectangle.left - 1
          && control.right <= rectangle.right + 1
          && control.top >= rectangle.top - 1
          && control.bottom <= rectangle.bottom + 1
        ))
      )),
      controlsOverlap,
    }
  })
  expect(geometry).toEqual({ count: 1, insideViewport: true, controlsOverlap: false })
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true)
  await expectNoHorizontalOverflow(page)
}

const openReferenceCodes = async (page: Page): Promise<void> => {
  const setsResponse = referenceResponse(page, 'GET')
  await page.goto('/app/reference-codes')
  expect((await setsResponse).status()).toBe(200)
  await observeLoadingState(page)
  const entriesResponse = codeListResponse(page)
  await page.getByLabel('Owner and set').selectOption({ index: 1 })
  expect((await entriesResponse).status()).toBe(200)
  await expectLoadingObserved(page)
  await expect(page.locator('[data-reference-codes-state="loading"]')).toBeHidden()
}

const runWorkflow = async (page: Page, tenantName: string, code: string): Promise<void> => {
  const errors = monitorFullStackErrors(page)
  await loginAndEnterTenant(page, tenantName)
  await openReferenceCodes(page)

  await page.getByLabel('As of').fill('2000-01-01T00:00:00.000Z')
  const historicalResponse = codeListResponse(page)
  await page.getByRole('button', { name: 'Apply as-of' }).click()
  expect((await historicalResponse).status()).toBe(200)
  await expect(page.locator('[data-reference-codes-state="empty-entries"]')).toBeVisible()

  const createButton = page.getByRole('button', { name: 'Create code' })
  await createButton.focus()
  await expect(createButton).toBeFocused()
  await page.keyboard.press('Enter')
  const createDialog = page.getByRole('dialog', { name: 'Create reference code' })
  await expectSingleUsableDialog(page, 'Create reference code')
  const createCode = createDialog.getByLabel('Code')
  const createLabel = createDialog.getByLabel('Label')
  await createCode.fill(code)
  await createCode.focus()
  await page.keyboard.press('Tab')
  await expect(createLabel).toBeFocused()
  await createLabel.fill('Initial label')
  await createDialog.getByLabel('Metadata JSON').fill('{"nested":{"invalid":true}}')
  await createDialog.getByRole('button', { name: 'Create' }).click()
  await expect(createDialog.getByRole('alert')).toContainText('REFERENCE_CODES_METADATA_INVALID')
  await createDialog.getByLabel('Metadata JSON').fill('{"source":"e2e"}')
  const createResponse = referenceResponse(page, 'POST')
  const createRefresh = codeListResponse(page)
  await createDialog.getByRole('button', { name: 'Create' }).click()
  const created = await createResponse
  expect(created.status()).toBe(201)
  expect((await createRefresh).status()).toBe(200)
  expect(created.request().headers()['if-none-match']).toBe('*')
  const createdEtag = created.headers().etag
  expect(createdEtag).toMatch(/^"rev-[1-9][0-9]*"$/)

  const row = page.locator(`[data-reference-code="${code}"]`)
  const appendButton = row.getByRole('button', { name: 'Append version' })
  await appendButton.focus()
  await page.keyboard.press('Enter')
  const appendDialog = page.getByRole('dialog', { name: 'Append reference-code version' })
  await expectSingleUsableDialog(page, 'Append reference-code version')
  await expect(appendDialog.getByLabel('Code')).toBeDisabled()
  await appendDialog.getByLabel('Label').fill('Updated label')

  const competitor = await page.context().newPage()
  try {
    const viewport = page.viewportSize()
    if (viewport !== null) await competitor.setViewportSize(viewport)
    await loginAndEnterTenant(competitor, tenantName)
    await openReferenceCodes(competitor)
    const competingRow = competitor.locator(`[data-reference-code="${code}"]`)
    await competingRow.getByRole('button', { name: 'Append version' }).click()
    const competingDialog = competitor.getByRole('dialog', { name: 'Append reference-code version' })
    await competingDialog.getByLabel('Label').fill('Competing label')
    const competingResponse = referenceResponse(competitor, 'PUT')
    const competingRefresh = codeListResponse(competitor)
    await competingDialog.getByRole('button', { name: 'Append version' }).click()
    const competingWrite = await competingResponse
    expect(competingWrite.status()).toBe(200)
    expect((await competingRefresh).status()).toBe(200)
    const competingEtag = competingWrite.headers().etag
    expect(competingEtag).toMatch(/^"rev-[1-9][0-9]*"$/)

    const staleResponse = referenceResponse(page, 'PUT')
    await appendDialog.getByRole('button', { name: 'Append version' }).click()
    expect((await staleResponse).status()).toBe(412)
    await expect(appendDialog.getByRole('alert')).toBeVisible()
    await expect(appendDialog.getByLabel('Label')).toHaveValue('Updated label')

    const staleDetail = detailResponse(page, code)
    const staleRefresh = codeListResponse(page)
    await appendDialog.getByRole('button', { name: 'Reload stale record' }).click()
    expect((await staleDetail).status()).toBe(200)
    expect((await staleRefresh).status()).toBe(200)
    await expect(appendDialog.getByRole('alert')).toBeHidden()
    await expect(appendDialog.getByLabel('Label')).toHaveValue('Updated label')

    const replaceResponse = referenceResponse(page, 'PUT')
    const replaceRefresh = codeListResponse(page)
    await appendDialog.getByRole('button', { name: 'Append version' }).click()
    const replaced = await replaceResponse
    expect(replaced.status()).toBe(200)
    expect((await replaceRefresh).status()).toBe(200)
    expect(replaced.request().headers()['if-match']).toBe(competingEtag)
  } finally {
    await competitor.close()
  }

  await page.getByLabel('As of').fill(new Date().toISOString())
  const currentResponse = codeListResponse(page)
  await page.getByRole('button', { name: 'Apply as-of' }).click()
  expect((await currentResponse).status()).toBe(200)
  await expect(row).toBeVisible()

  const retireButton = row.getByRole('button', { name: 'Retire' })
  await retireButton.focus()
  await page.keyboard.press('Enter')
  const retireDialog = page.getByRole('dialog', { name: 'Retire reference code' })
  await expectSingleUsableDialog(page, 'Retire reference code')
  await expect(retireDialog).toContainText('cannot be reused')
  const retireResponse = referenceResponse(page, 'DELETE')
  const retireRefresh = codeListResponse(page)
  await retireDialog.getByRole('button', { name: 'Retire permanently' }).click()
  expect((await retireResponse).status()).toBe(200)
  expect((await retireRefresh).status()).toBe(200)
  await expect(row).toBeVisible()

  await page.getByLabel('As of').fill(new Date().toISOString())
  const postRetireResponse = codeListResponse(page)
  await page.getByRole('button', { name: 'Apply as-of' }).click()
  expect((await postRetireResponse).status()).toBe(200)
  await expect(row).toHaveCount(0)

  const retiredResponse = codeListResponse(page)
  await page.getByRole('checkbox', { name: 'Include retired' }).check()
  expect((await retiredResponse).status()).toBe(200)
  await expect(row.getByText('retired', { exact: true })).toBeVisible()
  await expect(row.getByRole('button', { name: 'Retire' })).toBeDisabled()
  await expectNoHorizontalOverflow(page)
  expect(errors).toEqual([])
}

test('desktop real-backend reference-code workflow', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await runWorkflow(page, 'Alpha Team', `desktop-${Date.now()}`)
})

test('mobile real-backend reference-code workflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await runWorkflow(page, 'Beta Team', `mobile-${Date.now()}`)
})
