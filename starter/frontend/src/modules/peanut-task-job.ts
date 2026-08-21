import { createTaskJobModuleContribution, createTaskJobRuntime } from '@peanut-admin/admin/task-job'
import type { TaskJobRuntime, TaskJobTransport, TaskTransportResult } from '@peanut-admin/admin/task-job'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutTaskJobHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
  canManage?: () => boolean
}

export interface PeanutTaskJobHost { module: AdminModuleContribution; runtime: TaskJobRuntime }

const result = async (responsePromise: Promise<Response>): Promise<TaskTransportResult> => {
  const response = await responsePromise
  const text = await response.text()
  let body: unknown = null
  if (text !== '') { try { body = JSON.parse(text) as unknown } catch { body = null } }
  return { body, headers: response.headers, status: response.status }
}

export const createPeanutTaskJobHost = (options: PeanutTaskJobHostOptions): PeanutTaskJobHost => {
  const url = (path: string): string => new URL(path, options.baseUrl).toString()
  const transport: TaskJobTransport = {
    list: (status, page, pageSize, signal) => result(options.fetch(new Request(
      url(`/api/v1/tasks?status=${status}&page=${page}&page_size=${pageSize}`), { signal },
    ))),
    cancel: (jobKey, revision, signal) => result(options.fetch(new Request(
      url(`/api/v1/tasks/${encodeURIComponent(jobKey)}/cancel`), { method: 'POST', headers: { 'If-Match': `"rev-${revision}"` }, signal },
    ))),
    retry: (jobKey, revision, signal) => result(options.fetch(new Request(
      url(`/api/v1/tasks/${encodeURIComponent(jobKey)}/retry`), { method: 'POST', headers: { 'If-Match': `"rev-${revision}"` }, signal },
    ))),
  }
  const runtime = createTaskJobRuntime({
    transport,
    canRead: options.canRead,
    canManage: options.canManage ?? (() => false),
  })
  return { module: createTaskJobModuleContribution(runtime), runtime }
}
