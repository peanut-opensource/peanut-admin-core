import { defineAdminModule, hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import type { AudienceApiClient } from '@peanut-admin/admin/core'
import {
  IMPORT_EXPORT_CANCEL_PERMISSION,
  IMPORT_EXPORT_CREATE_PERMISSION,
  IMPORT_EXPORT_MODULE_KEY,
  IMPORT_EXPORT_READ_PERMISSION,
  IMPORT_EXPORT_ROUTE_NAME,
  IMPORT_EXPORT_ROUTE_PATH,
  IMPORT_EXPORT_STORE_KEY,
} from '@peanut-admin/admin/import-export'
import type { ImportExportRuntime, ImportExportTransport, ImportExportTransportResult } from '@peanut-admin/admin/import-export'
import { defineComponent, h, provide } from 'vue'

import { UNCONFIGURED_TENANT_CLIENT } from '../unconfigured-client'

interface ApiResult { readonly data?: unknown; readonly error?: unknown; readonly response: Response }

export interface PeanutImportExportModuleOptions {
  client: AudienceApiClient
}

const result = (value: ApiResult): ImportExportTransportResult => ({
  body: value.response.ok ? value.data : value.error,
  headers: value.response.headers,
  status: value.response.status,
})

export const createPeanutImportExportModule = (options: PeanutImportExportModuleOptions) => {
  const transport: ImportExportTransport = {
    async list(status, page, pageSize, signal) {
      return result(await options.client.GET('/api/v1/import-export/operations', {
        params: { query: { status, page, page_size: pageSize } }, signal,
      }))
    },
    async submitImport(providerKey, fileKey, mapping, idempotencyKey, signal) {
      return result(await options.client.POST('/api/v1/import-export/imports', {
        params: { header: { 'Idempotency-Key': idempotencyKey } },
        body: { provider_key: providerKey, file_key: fileKey, mapping },
        signal,
      }))
    },
    async submitExport(providerKey, idempotencyKey, signal) {
      return result(await options.client.POST('/api/v1/import-export/exports', {
        params: { header: { 'Idempotency-Key': idempotencyKey } },
        body: { provider_key: providerKey },
        signal,
      }))
    },
    async cancel(operationKey, revision, signal) {
      return result(await options.client.POST('/api/v1/import-export/operations/{operation_key}/cancel', {
        params: {
          path: { operation_key: operationKey },
          header: {
            'If-Match': `"rev-${revision}"`,
            'Idempotency-Key': `cancel-${operationKey}-${revision}`,
          },
        },
        signal,
      }))
    },
    download(fileKey, signal) {
      return fetch(`/api/v1/files/${encodeURIComponent(fileKey)}/content`, { credentials: 'same-origin', signal })
    },
  }
  let runtime: ImportExportRuntime | null = null

  const load = async () => {
    const feature = await import('@peanut-admin/admin/import-export')
    const active = runtime ?? feature.createImportExportRuntime({
      transport,
      canRead: () => hasPermission(useTenantContext().permissionSet, IMPORT_EXPORT_READ_PERMISSION),
      canCreate: () => hasPermission(useTenantContext().permissionSet, IMPORT_EXPORT_CREATE_PERMISSION),
      canCancel: () => hasPermission(useTenantContext().permissionSet, IMPORT_EXPORT_CANCEL_PERMISSION),
    })
    runtime = active
    const contribution = feature.createImportExportModuleContribution(active)
    const route = contribution.routes[0]
    if (contribution.key !== IMPORT_EXPORT_MODULE_KEY || contribution.routes.length !== 1
      || route?.name !== IMPORT_EXPORT_ROUTE_NAME || route.path !== IMPORT_EXPORT_ROUTE_PATH
    ) throw new Error('PEANUT_IMPORT_EXPORT_CONTRIBUTION_INVALID')
    const { default: Page } = await route.component()
    return { default: defineComponent({ setup() { provide(feature.importExportRuntimeKey, active); return () => h(Page) } }) }
  }

  return defineAdminModule({
    key: IMPORT_EXPORT_MODULE_KEY,
    routes: [{
      name: IMPORT_EXPORT_ROUTE_NAME,
      path: IMPORT_EXPORT_ROUTE_PATH,
      component: load,
      access: { moduleKey: IMPORT_EXPORT_MODULE_KEY, permissionKeys: [IMPORT_EXPORT_READ_PERMISSION] },
    }],
    disposeOnTenantChange: true,
    stores: [{ key: IMPORT_EXPORT_STORE_KEY, dispose() { runtime?.dispose(); runtime = null } }],
  })
}

export const peanutImportExportModule = createPeanutImportExportModule({ client: UNCONFIGURED_TENANT_CLIENT })
