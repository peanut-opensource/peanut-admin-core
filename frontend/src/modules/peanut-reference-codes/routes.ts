import { defineAdminModule, hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import type { AudienceApiClient } from '@peanut-admin/admin/core'
import {
  REFERENCE_CODES_MANAGE_PERMISSION,
  REFERENCE_CODES_MODULE_KEY,
  REFERENCE_CODES_READ_PERMISSION,
  REFERENCE_CODES_ROUTE_NAME,
  REFERENCE_CODES_ROUTE_PATH,
  REFERENCE_CODES_STORE_KEY,
} from '@peanut-admin/admin/reference-codes'
import type {
  ReferenceCodeCreateRequest,
  ReferenceCodeListQuery,
  ReferenceCodeReplaceRequest,
  ReferenceCodeRetireRequest,
  ReferenceCodesRuntime,
  ReferenceCodesTransport,
  ReferenceCodesTransportResult,
} from '@peanut-admin/admin/reference-codes'
import { defineComponent, h, provide } from 'vue'

import { UNCONFIGURED_TENANT_CLIENT } from '../unconfigured-client'

interface ApiClientResult {
  readonly data?: unknown
  readonly error?: unknown
  readonly response: Response
}

export interface PeanutReferenceCodesModuleOptions {
  client: AudienceApiClient
}

const transportResult = (result: ApiClientResult): ReferenceCodesTransportResult => ({
  body: result.response.ok ? result.data : result.error,
  headers: result.response.headers,
  status: result.response.status,
})

const versionBody = (input: ReferenceCodeReplaceRequest['input']) => ({
  label: input.label,
  metadata: input.metadata,
  status: input.status,
  sort_order: input.sortOrder,
  effective_at: input.effectiveAt,
  expires_at: input.expiresAt,
})

const listQuery = (query: ReferenceCodeListQuery) => ({
  as_of: query.asOf,
  effective_status: query.effectiveStatus,
  include_retired: query.includeRetired,
  page: query.page,
  page_size: query.pageSize,
})

const createHeaders = (request: ReferenceCodeCreateRequest) => ({
  'Idempotency-Key': request.idempotencyKey,
  'If-None-Match': '*' as const,
})

const mutationHeaders = (request: ReferenceCodeReplaceRequest | ReferenceCodeRetireRequest) => ({
  'Idempotency-Key': request.idempotencyKey,
  'If-Match': request.etag,
})

export const createPeanutReferenceCodesModule = (options: PeanutReferenceCodesModuleOptions) => {
  const transport: ReferenceCodesTransport = {
    async listSets(signal) {
      return transportResult(await options.client.GET('/api/v1/reference-code-sets', { signal }))
    },
    async listCodes(moduleKey, setKey, query, signal) {
      return transportResult(await options.client.GET(
        '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
        {
          params: {
            path: { module_key: moduleKey, set_key: setKey },
            query: listQuery(query),
          },
          signal,
        },
      ))
    },
    async getCode(moduleKey, setKey, code, asOf, signal) {
      return transportResult(await options.client.GET(
        '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
        {
          params: {
            path: { module_key: moduleKey, set_key: setKey, code },
            query: { as_of: asOf },
          },
          signal,
        },
      ))
    },
    async create(moduleKey, setKey, request) {
      return transportResult(await options.client.POST(
        '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
        {
          body: { code: request.input.code, ...versionBody(request.input) },
          params: {
            header: createHeaders(request),
            path: { module_key: moduleKey, set_key: setKey },
          },
          signal: request.signal,
        },
      ))
    },
    async replace(moduleKey, setKey, code, request) {
      return transportResult(await options.client.PUT(
        '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
        {
          body: versionBody(request.input),
          params: {
            header: mutationHeaders(request),
            path: { module_key: moduleKey, set_key: setKey, code },
          },
          signal: request.signal,
        },
      ))
    },
    async retire(moduleKey, setKey, code, request) {
      return transportResult(await options.client.DELETE(
        '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
        {
          params: {
            header: mutationHeaders(request),
            path: { module_key: moduleKey, set_key: setKey, code },
          },
          signal: request.signal,
        },
      ))
    },
  }
  let referenceCodesRuntime: ReferenceCodesRuntime | null = null

  const loadReferenceCodesRoute = async () => {
    const referenceCodesPackage = await import('@peanut-admin/admin/reference-codes')
    const runtime = referenceCodesRuntime ?? referenceCodesPackage.createReferenceCodesRuntime({
      transport,
      canRead: () => hasPermission(useTenantContext().permissionSet, REFERENCE_CODES_READ_PERMISSION),
      canManage: () => hasPermission(useTenantContext().permissionSet, REFERENCE_CODES_MANAGE_PERMISSION),
    })
    referenceCodesRuntime = runtime

    const contribution = referenceCodesPackage.createReferenceCodesModuleContribution(runtime)
    const packageRoute = contribution.routes[0]
    if (
      contribution.key !== REFERENCE_CODES_MODULE_KEY
      || contribution.routes.length !== 1
      || packageRoute?.name !== REFERENCE_CODES_ROUTE_NAME
      || packageRoute.path !== REFERENCE_CODES_ROUTE_PATH
      || packageRoute.access.moduleKey !== REFERENCE_CODES_MODULE_KEY
      || packageRoute.access.permissionKeys.length !== 1
      || packageRoute.access.permissionKeys[0] !== REFERENCE_CODES_READ_PERMISSION
    ) {
      runtime.dispose()
      throw new Error('PEANUT_REFERENCE_CODES_CONTRIBUTION_INVALID')
    }

    const { default: ReferenceCodesPage } = await packageRoute.component()
    return {
      default: defineComponent({
        name: 'PeanutReferenceCodesHostRoute',
        setup() {
          provide(referenceCodesPackage.referenceCodesRuntimeKey, runtime)
          return () => h(ReferenceCodesPage)
        },
      }),
    }
  }

  return defineAdminModule({
    key: REFERENCE_CODES_MODULE_KEY,
    routes: [{
      name: REFERENCE_CODES_ROUTE_NAME,
      path: REFERENCE_CODES_ROUTE_PATH,
      component: loadReferenceCodesRoute,
      access: {
        moduleKey: REFERENCE_CODES_MODULE_KEY,
        permissionKeys: [REFERENCE_CODES_READ_PERMISSION],
      },
    }],
    disposeOnTenantChange: true,
    stores: [{
      key: REFERENCE_CODES_STORE_KEY,
      dispose() {
        referenceCodesRuntime?.dispose()
        referenceCodesRuntime = null
      },
    }],
  })
}

export const peanutReferenceCodesModule = createPeanutReferenceCodesModule({
  client: UNCONFIGURED_TENANT_CLIENT,
})
