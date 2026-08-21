import { expect } from '@playwright/test'
import type { Page, TestInfo } from '@playwright/test'

export interface ObservedApiResponse {
  method: string
  path: string
  status: number
  requestId: string | undefined
}

export const browserPassword = (): string => {
  const password = process.env.PEANUT_BROWSER_PASSWORD
  if (password === undefined || password === '') {
    throw new Error('PEANUT_BROWSER_PASSWORD is required for full-stack browser tests.')
  }
  return password
}

export const observeApi = (page: Page): ObservedApiResponse[] => {
  const responses: ObservedApiResponse[] = []
  page.on('response', response => {
    const url = new URL(response.url())
    if (!url.pathname.startsWith('/api/')) return
    responses.push({
      method: response.request().method(),
      path: url.pathname,
      status: response.status(),
      requestId: response.headers()['x-request-id'],
    })
  })
  return responses
}

export const monitorFullStackErrors = (page: Page): string[] => {
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
      errors.push(message.text())
    }
  })
  return errors
}

export const expectApiResponse = (
  responses: ObservedApiResponse[],
  method: string,
  path: string,
  status: number,
): void => {
  const response = responses.find(candidate => (
    candidate.method === method && candidate.path === path && candidate.status === status
  ))
  expect(response, `${method} ${path} should return ${status}`).toBeDefined()
  expect(response?.requestId).toMatch(/^req_[a-zA-Z0-9_-]+$/)
}

export const captureFullStackScreenshot = async (
  page: Page,
  testInfo: TestInfo,
  name: string,
): Promise<void> => {
  await page.screenshot({ path: testInfo.outputPath(`${name}.png`), fullPage: true })
}
