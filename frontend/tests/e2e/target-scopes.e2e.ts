import { expect, test } from '@playwright/test'

import {
  captureAcceptanceScreenshot,
  createApiFixtureState,
  expectNoViewportOverflow,
  installApiFixture,
  loginTenant,
  monitorPageErrors,
} from '../fixtures/api'

test('zero targets disable commands without a manual ID bypass', async ({ page }, testInfo) => {
  const state = createApiFixtureState({ targetMode: 'zero' })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/examples/work-items')
  await expect(page.getByText('没有可用目标')).toBeVisible()
  await expect(page.getByRole('button', { name: '新建工作项' })).toBeDisabled()
  await expect(page.locator('.pa-target-selector')).toHaveCount(0)
  await captureAcceptanceScreenshot(page, testInfo, 'targets-zero')
  expect(errors).toEqual([])
})

test('single target is automatic and shared master stays one list', async ({ page }, testInfo) => {
  const state = createApiFixtureState({ targetMode: 'single' })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/examples/work-items')
  await expect(page.getByText('1 available target')).toBeVisible()
  await expect(page.locator('.pa-target-selector')).toHaveCount(0)
  await expect(page.getByRole('button', { name: '新建工作项' })).toBeEnabled()
  await page.getByRole('button', { name: '新建工作项' }).click()
  await expect(page.getByRole('dialog', { name: '新建工作项' })).toBeVisible()
  await page.getByRole('button', { name: '取消' }).click()

  await page.goto('/app/examples/references')
  await expect(page.getByText('Global Reference')).toBeVisible()
  await expect(page.getByText('Tenant Reference')).toBeVisible()
  await expect(page.locator('.resource-table')).toHaveCount(1)
  expect(await page.locator('body').textContent()).not.toContain('9007199254740993000')

  await expectNoViewportOverflow(page)
  await captureAcceptanceScreenshot(page, testInfo, 'shared-master-single-list')
  expect(errors).toEqual([])
})

test('multiple targets show ownership and aggregate remains read-only', async ({ page }, testInfo) => {
  const state = createApiFixtureState({ targetMode: 'multiple' })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/examples/work-items')
  await expect(page.locator('.pa-target-selector')).toBeVisible()
  await page.getByRole('button', { name: '选择全部已授权' }).click()
  await expect(page.getByText('3 of 3 targets selected')).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '归属目标' })).toBeVisible()
  await expect(page.locator('.resource-table').getByText('Project A')).toBeVisible()
  await expect(page.getByRole('button', { name: '新建工作项' })).toBeDisabled()

  await page.getByText('只读汇总', { exact: true }).click()
  await expect(page.getByText(/Read-only aggregate across 3 targets/)).toBeVisible()
  await expect(page.getByRole('button', { name: '新建工作项' })).toBeDisabled()

  await expectNoViewportOverflow(page)
  await captureAcceptanceScreenshot(page, testInfo, 'targets-multiple-aggregate')
  expect(errors).toEqual([])
})

test('policy candidate enumeration fails closed without delegation permission', async ({ page }) => {
  const state = createApiFixtureState({ policySelectionForbidden: true })
  const errors = monitorPageErrors(page)
  await installApiFixture(page, state)
  await loginTenant(page)

  await page.goto('/app/examples/work-item-policies')
  await expect(page.getByText('Delegation permission required.')).toBeVisible()
  await expect(page.getByRole('button', { name: '发布到所选目标' })).toBeDisabled()
  expect(errors).toEqual([])
})
