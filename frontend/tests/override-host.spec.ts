import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'

import type { AdminOverride } from '@peanut-admin/admin/core'
import {
  AdminShell,
  PlatformShell,
  WORKSPACE_SHELL_OVERRIDE_KEY,
} from '@peanut-admin/admin/shell'

import { createAdminRuntime } from '../src/app/runtime'

const config = {
  tenant: {
    baseUrl: 'https://tenant-api.example.test',
    allowedOrigin: 'https://tenant-api.example.test',
    clientKey: 'tenant-web',
  },
  platform: {
    baseUrl: 'https://platform-api.example.test',
    allowedOrigin: 'https://platform-api.example.test',
    clientKey: 'platform-web',
  },
}

const override = (value: unknown): AdminOverride => ({
  key: WORKSPACE_SHELL_OVERRIDE_KEY,
  kind: 'service',
  contractVersion: '1.0.0',
  value,
})

describe('reference Admin override Host wiring', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('resolves the package tenant and platform shells by default', () => {
    const runtime = createAdminRuntime(config)

    expect(runtime.workspaceShell('tenant')).toBe(AdminShell)
    expect(runtime.workspaceShell('platform')).toBe(PlatformShell)
    expect(runtime.overrides.resolve(WORKSPACE_SHELL_OVERRIDE_KEY).source).toBe('default')
    expect(runtime.overrides.diagnostics()).toEqual([{
      key: WORKSPACE_SHELL_OVERRIDE_KEY,
      kind: 'service',
      contractVersion: '1.0.0',
      source: 'default',
    }])
  })

  it('uses one explicit application resolver for both audiences', () => {
    const ApplicationShell = defineComponent({
      name: 'ApplicationShell',
      setup: () => () => h('main'),
    })
    const runtime = createAdminRuntime(config, {
      overrides: [override(() => ApplicationShell)],
    })

    expect(runtime.workspaceShell('tenant')).toBe(ApplicationShell)
    expect(runtime.workspaceShell('platform')).toBe(ApplicationShell)
    expect(runtime.overrides.resolve(WORKSPACE_SHELL_OVERRIDE_KEY).source).toBe('application')
  })

  it('fails runtime construction for an invalid resolver override', () => {
    expect(() => createAdminRuntime(config, {
      overrides: [override({})],
    })).toThrow(`ADMIN_OVERRIDE_VALUE_INVALID: ${WORKSPACE_SHELL_OVERRIDE_KEY}`)
  })

  it('fails when a selected resolver returns a non-component', () => {
    const runtime = createAdminRuntime(config, {
      overrides: [override(() => ({}))],
    })

    expect(() => runtime.workspaceShell('tenant')).toThrow('ADMIN_SHELL_OVERRIDE_RESULT_INVALID')
  })
})
