import {
  createSettingsFetchTransport,
  createSettingsModuleContribution,
  createSettingsRuntime,
} from '@peanut-admin/admin/settings'
import type { SettingsRuntime } from '@peanut-admin/admin/settings'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutSettingsHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
  canManage: () => boolean
}

export interface PeanutSettingsHost {
  module: AdminModuleContribution
  runtime: SettingsRuntime
}

export const createPeanutSettingsHost = (options: PeanutSettingsHostOptions): PeanutSettingsHost => {
  const runtime = createSettingsRuntime({
    transport: createSettingsFetchTransport({
      baseUrl: options.baseUrl,
      fetch: options.fetch,
    }),
    canRead: options.canRead,
    canManage: options.canManage,
  })

  return {
    module: createSettingsModuleContribution(runtime),
    runtime,
  }
}
