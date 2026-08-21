import type { AdminNavigationRoute } from '@peanut-admin/admin/core'
import { createOpsConsoleFetchTransport, createOpsConsoleRuntime, OpsConsolePage, opsConsoleRuntimeKey } from '@peanut-admin/admin/ops-console'
import type { OpsConsoleRuntime, OpsProviderOption } from '@peanut-admin/admin/ops-console'
import type { Component } from 'vue'
import { defineComponent, h, provide } from 'vue'

export interface PeanutOpsConsoleHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  opsProviders?: readonly OpsProviderOption[]
  opsMaintenanceReasons?: readonly string[]
  opsLogSources?: readonly string[]
  canReadOps?: () => boolean
  canBackup?: () => boolean
  canRestore?: () => boolean
  canMaintain?: () => boolean
  canReadLogs?: () => boolean
}
export interface PeanutOpsConsoleRoute extends AdminNavigationRoute {
  component: () => Promise<{ default: Component }>
}

export interface PeanutOpsConsoleHost { route: PeanutOpsConsoleRoute; runtime: OpsConsoleRuntime }

export const createPeanutOpsConsoleHost = (options: PeanutOpsConsoleHostOptions): PeanutOpsConsoleHost => {
  const denied = (): boolean => false
  const runtime = createOpsConsoleRuntime({
    transport: createOpsConsoleFetchTransport({ baseUrl: options.baseUrl, fetch: options.fetch }),
    providers: options.opsProviders ?? [], maintenanceReasons: options.opsMaintenanceReasons ?? [], logSources: options.opsLogSources ?? [],
    canRead: options.canReadOps ?? denied, canBackup: options.canBackup ?? denied, canRestore: options.canRestore ?? denied,
    canMaintain: options.canMaintain ?? denied, canReadLogs: options.canReadLogs ?? denied,
  })
  const page = defineComponent({ name: 'StarterOpsConsoleHostPage', setup() { provide(opsConsoleRuntimeKey, runtime); return () => h(OpsConsolePage) } })
  const route: PeanutOpsConsoleRoute = {
    name: 'peanut.ops-console.page',
    path: '/platform/ops',
    audience: 'platform',
    permission: 'platform.ops.read',
    component: async () => ({ default: page }),
  }
  return { route, runtime }
}
