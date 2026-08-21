import { defineConfig } from '@playwright/test'

const backendPort = Number(process.env.PEANUT_BROWSER_BACKEND_PORT ?? '4180')
if (!Number.isInteger(backendPort) || backendPort < 1 || backendPort > 65_535) {
  throw new Error('PEANUT_BROWSER_BACKEND_PORT must be an integer between 1 and 65535')
}
const frontendPort = Number(process.env.PEANUT_BROWSER_FRONTEND_PORT ?? '4173')
if (!Number.isInteger(frontendPort) || frontendPort < 1 || frontendPort > 65_535) {
  throw new Error('PEANUT_BROWSER_FRONTEND_PORT must be an integer between 1 and 65535')
}
const frontendUrl = `http://127.0.0.1:${frontendPort}`

export default defineConfig({
  testDir: './frontend/tests/e2e',
  testMatch: '**/*.e2e.ts',
  outputDir: '/tmp/peanut-admin-playwright-results',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['line']],
  use: {
    baseURL: frontendUrl,
    colorScheme: 'light',
    locale: 'zh-CN',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'desktop-chromium',
      testIgnore: '**/full-stack.e2e.ts',
      use: { browserName: 'chromium', viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'mobile-chromium',
      testIgnore: '**/full-stack.e2e.ts',
      use: { browserName: 'chromium', viewport: { width: 390, height: 844 } },
    },
    {
      name: 'full-stack-desktop',
      testMatch: '**/full-stack.e2e.ts',
      use: { browserName: 'chromium', viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'full-stack-mobile',
      testMatch: '**/full-stack.e2e.ts',
      use: { browserName: 'chromium', viewport: { width: 390, height: 844 } },
    },
  ],
  webServer: [
    {
      command: `php -S 127.0.0.1:${backendPort} -t backend/public backend/public/router.php`,
      url: `http://127.0.0.1:${backendPort}/api/v1/health`,
      reuseExistingServer: false,
      timeout: 120_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
    {
      command: `VITE_API_BASE_URL='' pnpm --filter @peanut-admin/reference-admin build && pnpm --filter @peanut-admin/reference-admin exec vite preview --config tests/fixtures/full-stack-vite.config.ts --host 127.0.0.1 --port ${frontendPort} --strictPort`,
      url: `${frontendUrl}/login`,
      reuseExistingServer: false,
      timeout: 120_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
  ],
})
