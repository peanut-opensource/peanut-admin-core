import { createFileMediaModuleContribution, createFileMediaRuntime } from '@peanut-admin/admin/file-media'
import type { FileMediaRuntime, FileMediaTransport, FileTransportResult } from '@peanut-admin/admin/file-media'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutFileMediaHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
  canCreate?: () => boolean
  canDelete?: () => boolean
}

export interface PeanutFileMediaHost {
  module: AdminModuleContribution
  runtime: FileMediaRuntime
}

const result = async (responsePromise: Promise<Response>): Promise<FileTransportResult> => {
  const response = await responsePromise
  const text = await response.text()
  let body: unknown = null
  if (text !== '') {
    try { body = JSON.parse(text) as unknown } catch { body = null }
  }
  return { body, headers: response.headers, status: response.status }
}

export const createPeanutFileMediaHost = (options: PeanutFileMediaHostOptions): PeanutFileMediaHost => {
  const url = (path: string): string => new URL(path, options.baseUrl).toString()
  const transport: FileMediaTransport = {
    list: (status, page, pageSize, signal) => result(options.fetch(new Request(
      url(`/api/v1/files?status=${status}&page=${page}&page_size=${pageSize}`), { signal },
    ))),
    assets: (page, pageSize, signal) => result(options.fetch(new Request(
      url(`/api/v1/file-assets?page=${page}&page_size=${pageSize}`), { signal },
    ))),
    upload: (file, signal) => {
      const body = new FormData()
      body.append('file', file)
      return result(options.fetch(new Request(url('/api/v1/files'), { method: 'POST', body, signal })))
    },
    download: (fileKey, signal) => options.fetch(new Request(url(`/api/v1/files/${encodeURIComponent(fileKey)}/content`), { signal })),
    archive: (fileKey, etag, signal) => result(options.fetch(new Request(
      url(`/api/v1/files/${encodeURIComponent(fileKey)}`), { method: 'DELETE', headers: { 'If-Match': etag }, signal },
    ))),
  }
  const runtime = createFileMediaRuntime({
    transport,
    canRead: options.canRead,
    canCreate: options.canCreate ?? (() => false),
    canDelete: options.canDelete ?? (() => false),
  })
  return { module: createFileMediaModuleContribution(runtime), runtime }
}
