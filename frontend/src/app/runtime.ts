import {
  createAdminOverrideRegistry,
  createPlatformApiClient,
  createAdminNavigationRegistry,
  createTenantApiClient,
  defineAdminHostConfig,
  disposeTenantState,
  useOperationTargets,
  usePlatformAuth,
  usePlatformContext,
  useTenantAuth,
  useTenantContext,
} from '@peanut-admin/admin/core'
import type {
  AdminHostConfig,
  AdminModuleContribution,
  AdminNavigationRegistry,
  AdminNavigationRoute,
  ApiAudience,
  AudienceApiClient,
  PlatformContextData,
  ProblemDetails,
  TenantContextData,
  AdminOverride,
} from '@peanut-admin/admin/core'
import {
  ADMIN_SHELL_OVERRIDE_SLOTS,
  resolveWorkspaceShell,
} from '@peanut-admin/admin/shell'
import type { AdminShellOverrideRegistry } from '@peanut-admin/admin/shell'
import type { Component } from 'vue'

import {
  authenticatedLogin,
  envelopeData,
  isRecord,
  menuItems,
  problemFromResponse,
  stringArray,
  stringValue,
  tenantLoginResult,
} from './contracts'
import type { AdminMenuItem, TenantLoginResult } from './contracts'
import { createContextGeneration } from './context-generation'
import { readAdminHostConfig } from './host-config'
import { createAppModules } from './modules'
import { ADMIN_HOST_OVERRIDES } from './overrides'
import { APP_ROUTE_REGISTRATIONS } from './routes'
import { useWorkspaceStore } from './store'
import type { WorkspaceIdentity } from './store'

interface FetchResult {
  data?: unknown
  error?: unknown
  response: Response
}

export class AdminApiError extends Error {
  public constructor(
    public readonly problem: ProblemDetails,
    public readonly retryAfter: string | null = null,
  ) {
    super(problem.code)
  }
}

const unwrap = (result: FetchResult): unknown => {
  if (result.response.ok) return result.data
  throw new AdminApiError(
    problemFromResponse(result.error, result.response),
    result.response.headers.get('Retry-After'),
  )
}

const responseJson = async (response: Response): Promise<unknown> => {
  const text = await response.text()
  if (text === '') return null
  try {
    return JSON.parse(text) as unknown
  } catch {
    return null
  }
}

export interface TenantContextView {
  context: TenantContextData
  identity: WorkspaceIdentity
}

export interface PlatformContextView {
  context: PlatformContextData
  identity: WorkspaceIdentity
}

const tenantContextView = (value: unknown): TenantContextView => {
  const data = envelopeData(value)
  if (!isRecord(data)) throw new Error('TENANT_CONTEXT_INVALID')
  const account = isRecord(data.account) ? data.account : {}
  const tenant = isRecord(data.tenant) ? data.tenant : {}
  const member = isRecord(data.member) ? data.member : {}
  const accountId = stringValue(account.id, stringValue(data.account_id))
  const tenantId = stringValue(tenant.id, stringValue(data.tenant_id))
  const memberId = stringValue(member.id, stringValue(data.tenant_member_id))
  if (data.audience !== 'tenant' || accountId === '' || tenantId === '' || memberId === '') {
    throw new Error('TENANT_CONTEXT_INVALID')
  }

  return {
    context: {
      audience: 'tenant',
      accountId,
      tenantId,
      memberId,
      moduleKeys: stringArray(data.module_keys),
      permissionKeys: stringArray(data.permission_keys),
      authorizationRevision: stringValue(data.authorization_revision, '1'),
    },
    identity: {
      accountLabel: stringValue(account.display_name, `Account ${accountId}`),
      contextLabel: stringValue(tenant.display_name, `Tenant ${tenantId}`),
      actorLabel: stringValue(member.display_name, `Member ${memberId}`),
    },
  }
}

const platformContextView = (value: unknown): PlatformContextView => {
  const data = envelopeData(value)
  if (!isRecord(data)) throw new Error('PLATFORM_CONTEXT_INVALID')
  const account = isRecord(data.account) ? data.account : {}
  const operator = isRecord(data.operator) ? data.operator : {}
  const accountId = stringValue(account.id, stringValue(data.account_id))
  const operatorId = stringValue(operator.id, stringValue(data.platform_operator_id))
  if (data.audience !== 'platform' || accountId === '' || operatorId === '') {
    throw new Error('PLATFORM_CONTEXT_INVALID')
  }

  return {
    context: {
      audience: 'platform',
      accountId,
      operatorId,
      permissionKeys: stringArray(data.permission_keys),
      authorizationRevision: stringValue(data.authorization_revision, '1'),
    },
    identity: {
      accountLabel: stringValue(account.display_name, `Account ${accountId}`),
      contextLabel: 'Platform control',
      actorLabel: stringValue(operator.display_name, `Operator ${operatorId}`),
    },
  }
}

export interface AdminRuntime {
  config: AdminHostConfig
  tenantClient: AudienceApiClient
  platformClient: AudienceApiClient
  modules: readonly AdminModuleContribution[]
  navigation: AdminNavigationRegistry
  routeRegistry: ReadonlyMap<string, AdminNavigationRoute>
  overrides: AdminShellOverrideRegistry
  generation: ReturnType<typeof createContextGeneration>
  workspaceShell: (audience: ApiAudience) => Component
  tenantLogin: (email: string, password: string, tenantCode: string | null) => Promise<TenantLoginResult>
  beginTenantSwitch: () => Promise<TenantLoginResult>
  selectTenant: (challengeToken: string, tenantId: string) => Promise<void>
  platformLogin: (email: string, password: string) => Promise<void>
  ensureContext: (audience: ApiAudience) => Promise<void>
  loadMenus: (audience: ApiAudience, force?: boolean) => Promise<AdminMenuItem[]>
  enterAudience: (audience: ApiAudience) => Promise<void>
  logout: (audience: ApiAudience) => Promise<void>
  unwrap: (result: FetchResult) => unknown
}

export interface AdminRuntimeDependencies {
  fetch?: (request: Request) => Promise<Response>
  overrides?: readonly AdminOverride[]
}

let installedRuntime: AdminRuntime | null = null

export const createAdminRuntime = (
  inputConfig: AdminHostConfig = readAdminHostConfig(),
  dependencies: AdminRuntimeDependencies = {},
): AdminRuntime => {
  const config = defineAdminHostConfig(inputConfig)
  const fetcher = dependencies.fetch ?? globalThis.fetch.bind(globalThis)
  const overrides = createAdminOverrideRegistry({
    slots: ADMIN_SHELL_OVERRIDE_SLOTS,
    overrides: dependencies.overrides ?? ADMIN_HOST_OVERRIDES,
  })
  const tenantAuth = useTenantAuth()
  const platformAuth = usePlatformAuth()
  const tenantContext = useTenantContext()
  const platformContext = usePlatformContext()
  const workspace = useWorkspaceStore()
  const targets = useOperationTargets()
  const generation = createContextGeneration()

  const refresh = async (audience: ApiAudience): Promise<string | null> => {
    const auth = audience === 'tenant' ? tenantAuth : platformAuth
    const audienceConfig = config[audience]
    auth.markRefreshing()
    const path = audience === 'tenant' ? '/api/v1/auth/refresh' : '/api/platform/v1/auth/refresh'
    const response = await fetcher(new Request(new URL(path, audienceConfig.baseUrl), {
      method: 'POST',
      credentials: 'include',
      redirect: 'manual',
      headers: { Accept: 'application/json' },
    }))
    const body = await responseJson(response)
    if (!response.ok) {
      auth.clear()
      return null
    }
    const data = envelopeData(body)
    if (!isRecord(data) || typeof data.access_token !== 'string') {
      auth.clear()
      return null
    }
    return data.access_token
  }

  const tenantClient = createTenantApiClient({
    baseUrl: config.tenant.baseUrl,
    allowedOrigin: config.tenant.allowedOrigin,
    fetch: fetcher,
    getAccessToken: () => tenantAuth.accessToken,
    setAccessToken: token => tenantAuth.replaceAccessToken(token),
    refresh: () => refresh('tenant'),
    refreshScope: `${config.tenant.clientKey}:tenant`,
  })
  const platformClient = createPlatformApiClient({
    baseUrl: config.platform.baseUrl,
    allowedOrigin: config.platform.allowedOrigin,
    fetch: fetcher,
    getAccessToken: () => platformAuth.accessToken,
    setAccessToken: token => platformAuth.replaceAccessToken(token),
    refresh: () => refresh('platform'),
    refreshScope: `${config.platform.clientKey}:platform`,
  })
  const modules = createAppModules({ tenantClient })
  const navigation = createAdminNavigationRegistry({ routes: APP_ROUTE_REGISTRATIONS, modules })
  const routeRegistry = new Map<string, AdminNavigationRoute>(
    navigation.routes().map(route => [route.name, route]),
  )

  const disposeRegisteredTenantState = async (): Promise<void> => {
    const results = await Promise.allSettled([
      disposeTenantState(),
      navigation.disposeTenantState(),
    ])
    const failure = results.find((result): result is PromiseRejectedResult => result.status === 'rejected')
    if (failure !== undefined) throw failure.reason
  }

  const clearTenantState = async (clearAuth: boolean): Promise<void> => {
    generation.advance()
    let disposalFailure: unknown = null
    try {
      await disposeRegisteredTenantState()
    } catch (error) {
      disposalFailure = error
    } finally {
      if (clearAuth) tenantAuth.clear()
      tenantContext.clear()
      workspace.clearTenant()
      targets.clearAll()
      navigation.clearDiagnostics()
    }
    if (disposalFailure !== null) throw disposalFailure
  }

  const clearAudience = async (audience: ApiAudience): Promise<void> => {
    if (audience === 'tenant') {
      await clearTenantState(true)
    } else {
      generation.advance()
      platformAuth.clear()
      platformContext.clear()
      workspace.clearPlatform()
    }
  }

  const runtime: AdminRuntime = {
    config,
    tenantClient,
    platformClient,
    modules,
    navigation,
    routeRegistry,
    overrides,
    generation,
    unwrap,
    workspaceShell: audience => resolveWorkspaceShell(overrides, audience),
    async tenantLogin(email, password, tenantCode) {
      await clearTenantState(true)
      const result = tenantLoginResult(unwrap(await tenantClient.POST('/api/v1/auth/login', {
        body: { email, password, tenant_code: tenantCode },
      })))
      if (result.state === 'authenticated') {
        tenantAuth.replaceAccessToken(result.accessToken)
      } else {
        workspace.tenantSelection = result
      }
      return result
    },
    async beginTenantSwitch() {
      const result = tenantLoginResult(unwrap(await tenantClient.POST('/api/v1/auth/tenant-switch/challenge')))
      if (result.state !== 'tenant_selection_required') throw new Error('AUTH_RESPONSE_INVALID')
      workspace.tenantSelection = result
      return result
    },
    async selectTenant(challengeToken, tenantId) {
      await clearTenantState(false)
      try {
        const result = authenticatedLogin(unwrap(await tenantClient.POST('/api/v1/auth/tenants/select', {
          body: { challenge_token: challengeToken, tenant_id: tenantId },
        })))
        tenantAuth.replaceAccessToken(result.accessToken)
      } catch (error) {
        tenantAuth.clear()
        throw error
      }
    },
    async platformLogin(email, password) {
      generation.advance()
      platformAuth.clear()
      platformContext.clear()
      workspace.clearPlatform()
      const result = authenticatedLogin(unwrap(await platformClient.POST('/api/platform/v1/auth/login', {
        body: { email, password },
      })))
      platformAuth.replaceAccessToken(result.accessToken)
    },
    async ensureContext(audience) {
      if (audience === 'tenant' && tenantContext.value !== null) return
      if (audience === 'platform' && platformContext.value !== null) return
      const ticket = generation.capture()
      try {
        if (audience === 'tenant') {
          const view = tenantContextView(unwrap(await tenantClient.GET('/api/v1/auth/context', {
            signal: ticket.signal,
          })))
          if (!ticket.isCurrent()) return
          tenantContext.replace(view.context)
          workspace.tenantIdentity = view.identity
        } else {
          const view = platformContextView(unwrap(await platformClient.GET('/api/platform/v1/auth/context', {
            signal: ticket.signal,
          })))
          if (!ticket.isCurrent()) return
          platformContext.replace(view.context)
          workspace.platformIdentity = view.identity
        }
      } catch (error) {
        if (!ticket.isCurrent()) return
        throw error
      }
    },
    async loadMenus(audience, force = false) {
      const revision = audience === 'tenant'
        ? tenantContext.authorizationRevision
        : platformContext.authorizationRevision
      const currentRevision = audience === 'tenant'
        ? workspace.tenantMenuRevision
        : workspace.platformMenuRevision
      const existing = audience === 'tenant' ? workspace.tenantMenus : workspace.platformMenus
      if (!force && existing.length > 0 && revision === currentRevision) return existing
      const ticket = generation.capture()
      let response: FetchResult
      try {
        response = audience === 'tenant'
          ? await tenantClient.GET('/api/v1/menus', { signal: ticket.signal })
          : await platformClient.GET('/api/platform/v1/menus', { signal: ticket.signal })
      } catch (error) {
        if (!ticket.isCurrent()) return []
        throw error
      }
      const menus = menuItems(unwrap(response))
      if (!ticket.isCurrent()) return []
      if (audience === 'tenant') {
        workspace.tenantMenus = menus
        workspace.tenantMenuRevision = revision
      } else {
        workspace.platformMenus = menus
        workspace.platformMenuRevision = revision
      }
      return menus
    },
    async enterAudience(audience) {
      if (workspace.activeAudience !== null && workspace.activeAudience !== audience) {
        await clearAudience(workspace.activeAudience)
      }
      workspace.activeAudience = audience
      workspace.problem = null
    },
    async logout(audience) {
      try {
        if (audience === 'tenant') {
          await tenantClient.POST('/api/v1/auth/logout')
        } else {
          await platformClient.POST('/api/platform/v1/auth/logout')
        }
      } finally {
        await clearAudience(audience)
        workspace.activeAudience = null
      }
    },
  }

  return runtime
}

export const installAdminRuntime = (runtime: AdminRuntime): void => {
  installedRuntime = runtime
}

export const useAdminRuntime = (): AdminRuntime => {
  if (installedRuntime === null) throw new Error('ADMIN_RUNTIME_NOT_INSTALLED')
  return installedRuntime
}
