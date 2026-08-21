import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

import { createStarterModules } from '../src/app/modules'

describe('internal starter Task/Job consumption', () => {
  it('composes the package route and uses the host transport', async () => {
    expect(existsSync(new URL('../src/modules/peanut-task-job.ts', import.meta.url))).toBe(true)
    const host = createStarterModules({
      baseUrl: 'https://starter.example.test', canRead: () => true, canManage: () => false,
      fetch: async () => new Response(JSON.stringify({
        data: { items: [] }, meta: { request_id: 'req_tasks', page: 1, page_size: 20, total: 0 },
      }), { status: 200, headers: { 'Content-Type': 'application/json' } }),
    })
    expect(host.modules.map(module => module.key)).toEqual([
      'example.greeting', 'peanut.settings', 'peanut.reference-codes', 'peanut.file-media', 'peanut.task-job', 'peanut.notification-sms', 'peanut.import-export', 'peanut.integration-security',
    ])
    expect(host.taskJobModule.routes[0]).toMatchObject({ name: 'peanut.task-job.list', path: '/app/tasks' })
    await host.taskJobRuntime.load()
    expect(host.taskJobRuntime.state.items).toEqual([])
    host.taskJobRuntime.dispose()
  })
})
