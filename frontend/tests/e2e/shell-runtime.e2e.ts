import { expect, test } from '@playwright/test'

import {
  createApiFixtureState,
  expectNoViewportOverflow,
  installApiFixture,
  loginPlatform,
  loginTenant,
  monitorPageErrors,
  openNavigationIfNeeded,
} from '../fixtures/api'

test('tenant shell supports desktop collapse and mobile Drawer navigation', async ({ page }) => {
  const errors = monitorPageErrors(page)
  await installApiFixture(page, createApiFixtureState())
  await loginTenant(page)

  const shell = page.locator('.pa-shell')
  await expect(shell).toHaveAttribute('data-audience', 'tenant')
  await expect(page.locator('.pa-shell-breadcrumb')).toContainText('工作台')

  if ((page.viewportSize()?.width ?? 0) <= 760) {
    await openNavigationIfNeeded(page)
    const drawer = page.locator('.mobile-navigation-drawer')
    await expect(drawer.getByText('Alpha Team')).toBeVisible()
    await expect(drawer.getByRole('button', { name: '切换租户' })).toBeVisible()
    await expect(drawer.locator('.el-drawer__close-btn')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(drawer).toBeHidden()
    await openNavigationIfNeeded(page)
    await drawer.getByRole('link', { name: '成员管理' }).click()
    await expect(drawer).toBeHidden()
  } else {
    await expect(page.getByRole('button', { name: '切换租户' })).toBeVisible()
    const shellWidth = await shell.evaluate(element => element.getBoundingClientRect().width)
    const sidebar = page.locator('.pa-shell-sidebar')
    const expandedWidth = await sidebar.evaluate(element => element.getBoundingClientRect().width)
    const collapse = page.getByRole('button', { name: '收起导航' })
    await collapse.focus()
    await expect(collapse).toBeFocused()
    await collapse.click()
    await expect.poll(() => sidebar.evaluate(element => element.getBoundingClientRect().width)).toBeLessThan(expandedWidth)
    const collapsedWidth = await sidebar.evaluate(element => element.getBoundingClientRect().width)
    expect(collapsedWidth).toBeLessThan(expandedWidth)
    expect(await shell.evaluate(element => element.getBoundingClientRect().width)).toBe(shellWidth)
  }

  await expectNoViewportOverflow(page)
  expect(errors).toEqual([])
})

test('platform shell never exposes Tenant identity or switching', async ({ page }) => {
  const errors = monitorPageErrors(page)
  await installApiFixture(page, createApiFixtureState())
  await loginPlatform(page)

  await expect(page.locator('.pa-shell')).toHaveAttribute('data-audience', 'platform')
  await openNavigationIfNeeded(page)
  const mobile = (page.viewportSize()?.width ?? 0) <= 760
  if (mobile) {
    await expect(page.getByRole('button', { name: '打开导航' })).toHaveAttribute('aria-expanded', 'true')
  }
  const identityRegion = mobile
    ? page.locator('.mobile-navigation-drawer')
    : page.getByRole('banner')
  await expect(identityRegion.getByText('Platform Owner', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '切换租户' })).toHaveCount(0)
  await expect(page.getByText('成员管理', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: '退出' }).last()).toBeVisible()
  if (mobile) {
    await expect.poll(() => page.locator('.mobile-navigation-drawer').getByRole('link', { name: '工作台' })
      .evaluate(element => element.getBoundingClientRect().left)).toBeGreaterThanOrEqual(-1)
  }
  await expectNoViewportOverflow(page)
  expect(errors).toEqual([])
})
