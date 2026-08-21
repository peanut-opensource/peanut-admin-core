import { defineAdminModule, hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import type { AudienceApiClient, components } from '@peanut-admin/admin/core'
import {
  SETTINGS_MANAGE_PERMISSION,
  SETTINGS_MODULE_KEY,
  SETTINGS_READ_PERMISSION,
  SETTINGS_ROUTE_NAME,
  SETTINGS_ROUTE_PATH,
  SETTINGS_STORE_KEY,
} from '@peanut-admin/admin/settings'
import type {
  ReplaceSettingRequest,
  SettingsRuntime,
  SettingsTransport,
  SettingsTransportResult,
  UnsetSettingRequest,
} from '@peanut-admin/admin/settings'
import { defineComponent, h, provide } from 'vue'

import { UNCONFIGURED_TENANT_CLIENT } from '../unconfigured-client'

interface ApiClientResult {
  data?: unknown
  error?: unknown
  response: Response
}

export interface PeanutSettingsModuleOptions {
  client: AudienceApiClient
}

type SettingValue = components['schemas']['JsonValue']

const transportResult = (result: ApiClientResult): SettingsTransportResult => ({
  body: result.response.ok ? result.data : result.error,
  headers: result.response.headers,
  status: result.response.status,
})

const replaceHeaders = (request: ReplaceSettingRequest) => request.precondition.kind === 'create'
  ? { 'Idempotency-Key': request.idempotencyKey, 'If-None-Match': '*' as const }
  : { 'Idempotency-Key': request.idempotencyKey, 'If-Match': request.precondition.etag }

const unsetHeaders = (request: UnsetSettingRequest) => ({
  'Idempotency-Key': request.idempotencyKey,
  'If-Match': request.etag,
})

const settingValue = (value: unknown): SettingValue => {
  if (typeof value === 'string' || typeof value === 'boolean') return value
  if (typeof value === 'number' && Number.isFinite(value)) return value
  throw new Error('SETTINGS_VALUE_INVALID')
}

export const createPeanutSettingsModule = (options: PeanutSettingsModuleOptions) => {
  const transport: SettingsTransport = {
    async list(signal) {
      return transportResult(await options.client.GET('/api/v1/settings', { signal }))
    },
    async replace(moduleKey, settingKey, request) {
      return transportResult(await options.client.PUT('/api/v1/settings/{module_key}/{setting_key}', {
        body: { value: settingValue(request.value) },
        params: {
          header: replaceHeaders(request),
          path: { module_key: moduleKey, setting_key: settingKey },
        },
        signal: request.signal,
      }))
    },
    async unset(moduleKey, settingKey, request) {
      return transportResult(await options.client.DELETE('/api/v1/settings/{module_key}/{setting_key}', {
        params: {
          header: unsetHeaders(request),
          path: { module_key: moduleKey, setting_key: settingKey },
        },
        signal: request.signal,
      }))
    },
  }
  let settingsRuntime: SettingsRuntime | null = null

  const loadSettingsRoute = async () => {
    const settingsPackage = await import('@peanut-admin/admin/settings')
    const runtime = settingsRuntime ?? settingsPackage.createSettingsRuntime({
      transport,
      canRead: () => hasPermission(useTenantContext().permissionSet, SETTINGS_READ_PERMISSION),
      canManage: () => hasPermission(useTenantContext().permissionSet, SETTINGS_MANAGE_PERMISSION),
    })
    settingsRuntime = runtime

    const packageContribution = settingsPackage.createSettingsModuleContribution(runtime)
    const packageRoute = packageContribution.routes[0]
    if (packageContribution.key !== SETTINGS_MODULE_KEY
      || packageRoute?.name !== SETTINGS_ROUTE_NAME
      || packageRoute.path !== SETTINGS_ROUTE_PATH
      || packageRoute.access.moduleKey !== SETTINGS_MODULE_KEY
      || !packageRoute.access.permissionKeys.includes(SETTINGS_READ_PERMISSION)) {
      throw new Error('PEANUT_SETTINGS_CONTRIBUTION_INVALID')
    }

    const { default: SettingsPage } = await packageRoute.component()
    return {
      default: defineComponent({
        name: 'PeanutSettingsHostRoute',
        setup() {
          provide(settingsPackage.settingsRuntimeKey, runtime)
          return () => h(SettingsPage)
        },
      }),
    }
  }

  return defineAdminModule({
    key: SETTINGS_MODULE_KEY,
    routes: [{
      name: SETTINGS_ROUTE_NAME,
      path: SETTINGS_ROUTE_PATH,
      component: loadSettingsRoute,
      access: {
        moduleKey: SETTINGS_MODULE_KEY,
        permissionKeys: [SETTINGS_READ_PERMISSION],
      },
    }],
    disposeOnTenantChange: true,
    stores: [{
      key: SETTINGS_STORE_KEY,
      dispose() {
        settingsRuntime?.dispose()
        settingsRuntime = null
      },
    }],
  })
}

export const peanutSettingsModule = createPeanutSettingsModule({ client: UNCONFIGURED_TENANT_CLIENT })
