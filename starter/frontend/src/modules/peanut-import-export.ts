import { createImportExportModuleContribution, createImportExportRuntime } from '@peanut-admin/admin/import-export'
import type { ImportExportRuntime, ImportExportTransport, ImportExportTransportResult } from '@peanut-admin/admin/import-export'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutImportExportHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
  canCreate?: () => boolean
  canCancel?: () => boolean
}

export interface PeanutImportExportHost { module: AdminModuleContribution; runtime: ImportExportRuntime }

const result = async (responsePromise: Promise<Response>): Promise<ImportExportTransportResult> => {
  const response = await responsePromise
  const text = await response.text()
  let body: unknown = null
  if (text !== '') { try { body = JSON.parse(text) as unknown } catch { body = null } }
  return { body, headers: response.headers, status: response.status }
}

export const createPeanutImportExportHost = (options: PeanutImportExportHostOptions): PeanutImportExportHost => {
  const url = (path: string): string => new URL(path, options.baseUrl).toString()
  const request = (path: string, init: RequestInit): Promise<ImportExportTransportResult> => result(options.fetch(new Request(url(path), { credentials: 'include', ...init })))
  const transport: ImportExportTransport = {
    list: (status, page, pageSize, signal) => request(`/api/v1/import-export/operations?status=${status}&page=${page}&page_size=${pageSize}`, { signal }),
    submitImport: (providerKey, fileKey, mapping, idempotencyKey, signal) => request('/api/v1/import-export/imports', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey }, body: JSON.stringify({ provider_key: providerKey, file_key: fileKey, mapping }), signal }),
    submitExport: (providerKey, idempotencyKey, signal) => request('/api/v1/import-export/exports', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey }, body: JSON.stringify({ provider_key: providerKey }), signal }),
    cancel: (operationKey, revision, signal) => request(`/api/v1/import-export/operations/${encodeURIComponent(operationKey)}/cancel`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'If-Match': `"rev-${revision}"`, 'Idempotency-Key': `cancel-${operationKey}-${revision}` }, body: '{}', signal }),
    download: (fileKey, signal) => options.fetch(new Request(url(`/api/v1/files/${encodeURIComponent(fileKey)}/content`), { credentials: 'include', signal })),
  }
  const runtime = createImportExportRuntime({ transport, canRead: options.canRead, canCreate: options.canCreate ?? (() => false), canCancel: options.canCancel ?? (() => false) })
  return { module: createImportExportModuleContribution(runtime), runtime }
}
