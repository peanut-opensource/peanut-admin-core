import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

import { createStarterModules } from '../src/app/modules'

describe('internal starter Ops Console consumption', () => {
  it('registers the platform workbench route with fail-closed permissions', async () => {
    expect(existsSync(new URL('../src/modules/peanut-ops-console.ts', import.meta.url))).toBe(true)
    const host = createStarterModules({
      baseUrl: 'https://starter.example.test', canRead: () => true, canManage: () => false,
      fetch: async () => { throw new Error('not called') },
    })
    expect(host.opsConsoleRoute).toMatchObject({ name: 'peanut.ops-console.page', path: '/platform/ops', audience: 'platform', permission: 'platform.ops.read' })
    expect(host.opsConsoleRoute.component).toEqual(expect.any(Function))
    expect(host.modules.map(module => module.key)).not.toContain('peanut.ops-console')
    expect(host.opsConsoleRuntime.canBackup()).toBe(false)
    host.opsConsoleRuntime.dispose()
  })
})
