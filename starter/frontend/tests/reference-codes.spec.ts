import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import * as referenceCodesPackage from '@peanut-admin/admin/reference-codes'

import { createStarterModules } from '../src/app/modules'

describe('internal starter reference-code consumption', () => {
  it('composes the package contribution through the generated package root', async () => {
    expect(existsSync(new URL('../src/app/modules.ts', import.meta.url))).toBe(true)
    expect(existsSync(new URL('../src/modules/peanut-reference-codes.ts', import.meta.url))).toBe(true)

    const host = createStarterModules({
      baseUrl: 'https://starter.example.test',
      canManage: () => false,
      canRead: () => true,
      fetch: async () => new Response(JSON.stringify({
        data: { items: [] },
        meta: { request_id: 'req_starter_reference_codes' },
      }), {
        headers: { 'Content-Type': 'application/json', 'X-Request-Id': 'req_starter_reference_codes' },
        status: 200,
      }),
    })

    expect(referenceCodesPackage.REFERENCE_CODES_VERSION).toBe('0.1.0')
    expect(host.modules.map(module => module.key)).toEqual([
      'example.greeting',
      'peanut.settings',
      'peanut.reference-codes',
      'peanut.file-media',
      'peanut.task-job',
      'peanut.notification-sms', 'peanut.import-export', 'peanut.integration-security',
    ])
    expect(host.referenceCodesModule.routes[0]).toMatchObject({
      name: referenceCodesPackage.REFERENCE_CODES_ROUTE_NAME,
      path: referenceCodesPackage.REFERENCE_CODES_ROUTE_PATH,
      access: {
        moduleKey: referenceCodesPackage.REFERENCE_CODES_MODULE_KEY,
        permissionKeys: [referenceCodesPackage.REFERENCE_CODES_READ_PERMISSION],
      },
    })

    await host.referenceCodesRuntime.loadSets()
    expect(host.referenceCodesRuntime.state.sets).toEqual([])
    host.referenceCodesRuntime.dispose()
  })
})
