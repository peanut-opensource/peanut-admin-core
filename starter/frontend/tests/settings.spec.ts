import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import * as settingsPackage from '@peanut-admin/admin/settings'

import { createStarterModules } from '../src/app/modules'

describe('internal starter Settings consumption', () => {
  it('composes the package contribution through the generated package root', async () => {
    expect(existsSync(new URL('../src/app/modules.ts', import.meta.url))).toBe(true)
    expect(existsSync(new URL('../src/modules/peanut-settings.ts', import.meta.url))).toBe(true)

    let request: Request | null = null
    const host = createStarterModules({
      baseUrl: 'https://starter.example.test',
      canManage: () => false,
      canRead: () => true,
      fetch: async input => {
        request = input
        return new Response(JSON.stringify({ data: { items: [] } }), {
          headers: {
            'Content-Type': 'application/json',
            ETag: '"settings-collection-1"',
          },
          status: 200,
        })
      },
    })

    expect(settingsPackage.SETTINGS_VERSION).toBe('0.1.0')
    expect(host.modules.map(module => module.key)).toEqual([
      'example.greeting',
      'peanut.settings',
      'peanut.reference-codes',
      'peanut.file-media',
      'peanut.task-job',
      'peanut.notification-sms', 'peanut.import-export', 'peanut.integration-security',
    ])
    expect(host.settingsModule.routes[0]).toMatchObject({
      name: settingsPackage.SETTINGS_ROUTE_NAME,
      path: settingsPackage.SETTINGS_ROUTE_PATH,
      access: {
        moduleKey: settingsPackage.SETTINGS_MODULE_KEY,
        permissionKeys: [settingsPackage.SETTINGS_READ_PERMISSION],
      },
    })

    await host.settingsRuntime.load()
    expect(request?.url).toBe('https://starter.example.test/api/v1/settings')
    expect(request?.credentials).toBe('include')
    expect(host.settingsRuntime.state.records).toEqual([])
    host.settingsRuntime.dispose()
  })
})
