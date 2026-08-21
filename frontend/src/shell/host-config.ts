import { defineShellHostConfig } from '@peanut-admin/admin/shell'
import type { ShellHostConfig } from '@peanut-admin/admin/shell'

type ShellEnvironment = Readonly<Record<string, unknown>>

const readEnvironmentValue = (environment: ShellEnvironment, key: string): string | undefined => {
  const value = environment[key]
  return typeof value === 'string' ? value : undefined
}

export const readShellHostConfig = (environment: ShellEnvironment): ShellHostConfig => defineShellHostConfig({
  brand: {
    name: readEnvironmentValue(environment, 'VITE_ADMIN_BRAND_NAME') ?? 'Peanut Admin',
    mark: readEnvironmentValue(environment, 'VITE_ADMIN_BRAND_MARK') ?? 'P',
  },
  audiences: {
    tenant: { label: readEnvironmentValue(environment, 'VITE_ADMIN_TENANT_LABEL') ?? '租户工作区' },
    platform: { label: readEnvironmentValue(environment, 'VITE_ADMIN_PLATFORM_LABEL') ?? '平台控制面' },
  },
  commands: {
    switchTenantLabel: '切换租户',
    logoutLabel: '退出',
  },
})
