import { defineAdminModule, hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import type { AudienceApiClient } from '@peanut-admin/admin/core'
import {
  FILE_MEDIA_CREATE_PERMISSION,
  FILE_MEDIA_DELETE_PERMISSION,
  FILE_MEDIA_MODULE_KEY,
  FILE_MEDIA_READ_PERMISSION,
  FILE_MEDIA_ROUTE_NAME,
  FILE_MEDIA_ROUTE_PATH,
  FILE_MEDIA_STORE_KEY,
} from '@peanut-admin/admin/file-media'
import type { FileMediaRuntime, FileMediaTransport, FileTransportResult } from '@peanut-admin/admin/file-media'
import { defineComponent, h, provide } from 'vue'

import { UNCONFIGURED_TENANT_CLIENT } from '../unconfigured-client'

interface ApiClientResult {
  readonly data?: unknown
  readonly error?: unknown
  readonly response: Response
}

export interface PeanutFileMediaModuleOptions {
  client: AudienceApiClient
}

const transportResult = (result: ApiClientResult): FileTransportResult => ({
  body: result.response.ok ? result.data : result.error,
  headers: result.response.headers,
  status: result.response.status,
})

export const createPeanutFileMediaModule = (options: PeanutFileMediaModuleOptions) => {
  const transport: FileMediaTransport = {
    async list(status, page, pageSize, signal) {
      return transportResult(await options.client.GET('/api/v1/files', {
        params: { query: { status, page, page_size: pageSize } }, signal,
      }))
    },
    async assets(page, pageSize, signal) {
      return transportResult(await options.client.GET('/api/v1/file-assets', {
        params: { query: { page, page_size: pageSize } }, signal,
      }))
    },
    async upload(file, signal) {
      return transportResult(await options.client.POST('/api/v1/files', {
        body: { file } as never,
        bodySerializer(body) {
          const form = new FormData()
          form.append('file', (body as unknown as { file: File }).file)
          return form
        },
        signal,
      }))
    },
    async download(fileKey, signal) {
      return (await options.client.GET('/api/v1/files/{file_key}/content', {
        params: { path: { file_key: fileKey } }, parseAs: 'blob', signal,
      })).response
    },
    async archive(fileKey, etag, signal) {
      return transportResult(await options.client.DELETE('/api/v1/files/{file_key}', {
        params: { header: { 'If-Match': etag }, path: { file_key: fileKey } }, signal,
      }))
    },
  }
  let runtime: FileMediaRuntime | null = null

  const loadFileMediaRoute = async () => {
    const fileMedia = await import('@peanut-admin/admin/file-media')
    const active = runtime ?? fileMedia.createFileMediaRuntime({
      transport,
      canRead: () => hasPermission(useTenantContext().permissionSet, FILE_MEDIA_READ_PERMISSION),
      canCreate: () => hasPermission(useTenantContext().permissionSet, FILE_MEDIA_CREATE_PERMISSION),
      canDelete: () => hasPermission(useTenantContext().permissionSet, FILE_MEDIA_DELETE_PERMISSION),
    })
    runtime = active
    const contribution = fileMedia.createFileMediaModuleContribution(active)
    const route = contribution.routes[0]
    if (
      contribution.key !== FILE_MEDIA_MODULE_KEY || contribution.routes.length !== 1
      || route?.name !== FILE_MEDIA_ROUTE_NAME || route.path !== FILE_MEDIA_ROUTE_PATH
      || route.access.moduleKey !== FILE_MEDIA_MODULE_KEY
      || route.access.permissionKeys[0] !== FILE_MEDIA_READ_PERMISSION
    ) {
      active.dispose()
      throw new Error('PEANUT_FILE_MEDIA_CONTRIBUTION_INVALID')
    }
    const { default: Page } = await route.component()
    return { default: defineComponent({ setup() { provide(fileMedia.fileMediaRuntimeKey, active); return () => h(Page) } }) }
  }

  return defineAdminModule({
    key: FILE_MEDIA_MODULE_KEY,
    routes: [{
      name: FILE_MEDIA_ROUTE_NAME,
      path: FILE_MEDIA_ROUTE_PATH,
      component: loadFileMediaRoute,
      access: { moduleKey: FILE_MEDIA_MODULE_KEY, permissionKeys: [FILE_MEDIA_READ_PERMISSION] },
    }],
    disposeOnTenantChange: true,
    stores: [{ key: FILE_MEDIA_STORE_KEY, dispose() { runtime?.dispose(); runtime = null } }],
  })
}

export const peanutFileMediaModule = createPeanutFileMediaModule({ client: UNCONFIGURED_TENANT_CLIENT })
