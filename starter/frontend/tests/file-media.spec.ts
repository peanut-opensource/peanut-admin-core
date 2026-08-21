import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import * as fileMedia from '@peanut-admin/admin/file-media'

import { createStarterModules } from '../src/app/modules'

describe('internal starter file-media consumption', () => {
  it('composes the package contribution without a second demo', async () => {
    expect(existsSync(new URL('../src/modules/peanut-file-media.ts', import.meta.url))).toBe(true)
    const host = createStarterModules({
      baseUrl: 'https://starter.example.test', canRead: () => true,
      canManage: () => false, canCreate: () => true, canDelete: () => false,
      fetch: async request => new Response(JSON.stringify({
        data: { items: [] }, meta: { request_id: 'req_starter_files', page: 1, page_size: 20, total: 0 },
      }), { status: request.method === 'GET' ? 200 : 201, headers: { 'Content-Type': 'application/json' } }),
    })
    expect(fileMedia.FILE_MEDIA_VERSION).toBe('0.1.0')
    expect(host.modules.map(module => module.key)).toEqual([
      'example.greeting', 'peanut.settings', 'peanut.reference-codes', 'peanut.file-media', 'peanut.task-job', 'peanut.notification-sms', 'peanut.import-export', 'peanut.integration-security',
    ])
    expect(host.fileMediaModule.routes[0]).toMatchObject({
      name: 'peanut.file-media.list', path: '/app/files',
      access: { moduleKey: 'peanut.file-media', permissionKeys: ['peanut.file-media.read'] },
    })
    await host.fileMediaRuntime.load()
    expect(host.fileMediaRuntime.state.items).toEqual([])
    host.fileMediaRuntime.dispose()
  })
})
