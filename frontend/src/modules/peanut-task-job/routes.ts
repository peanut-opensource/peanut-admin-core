import { defineAdminModule, hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import type { AudienceApiClient } from '@peanut-admin/admin/core'
import {
  TASK_JOB_MANAGE_PERMISSION,
  TASK_JOB_MODULE_KEY,
  TASK_JOB_READ_PERMISSION,
  TASK_JOB_ROUTE_NAME,
  TASK_JOB_ROUTE_PATH,
  TASK_JOB_STORE_KEY,
} from '@peanut-admin/admin/task-job'
import type { TaskJobRuntime, TaskJobTransport, TaskTransportResult } from '@peanut-admin/admin/task-job'
import { defineComponent, h, provide } from 'vue'

import { UNCONFIGURED_TENANT_CLIENT } from '../unconfigured-client'

interface ApiClientResult { readonly data?: unknown; readonly error?: unknown; readonly response: Response }

export interface PeanutTaskJobModuleOptions {
  client: AudienceApiClient
}

const result = (value: ApiClientResult): TaskTransportResult => ({
  body: value.response.ok ? value.data : value.error,
  headers: value.response.headers,
  status: value.response.status,
})

export const createPeanutTaskJobModule = (options: PeanutTaskJobModuleOptions) => {
  const transport: TaskJobTransport = {
    async list(status, page, pageSize, signal) {
      return result(await options.client.GET('/api/v1/tasks', {
        params: { query: { status, page, page_size: pageSize } }, signal,
      }))
    },
    async cancel(jobKey, revision, signal) {
      return result(await options.client.POST('/api/v1/tasks/{job_key}/cancel', {
        params: { path: { job_key: jobKey }, header: { 'If-Match': `"rev-${revision}"` } }, signal,
      }))
    },
    async retry(jobKey, revision, signal) {
      return result(await options.client.POST('/api/v1/tasks/{job_key}/retry', {
        params: { path: { job_key: jobKey }, header: { 'If-Match': `"rev-${revision}"` } }, signal,
      }))
    },
  }
  let runtime: TaskJobRuntime | null = null

  const loadTaskJobRoute = async () => {
    const taskJob = await import('@peanut-admin/admin/task-job')
    const active = runtime ?? taskJob.createTaskJobRuntime({
      transport,
      canRead: () => hasPermission(useTenantContext().permissionSet, TASK_JOB_READ_PERMISSION),
      canManage: () => hasPermission(useTenantContext().permissionSet, TASK_JOB_MANAGE_PERMISSION),
    })
    runtime = active
    const contribution = taskJob.createTaskJobModuleContribution(active)
    const route = contribution.routes[0]
    if (contribution.key !== TASK_JOB_MODULE_KEY || contribution.routes.length !== 1
      || route?.name !== TASK_JOB_ROUTE_NAME || route.path !== TASK_JOB_ROUTE_PATH
    ) throw new Error('PEANUT_TASK_JOB_CONTRIBUTION_INVALID')
    const { default: Page } = await route.component()
    return { default: defineComponent({ setup() { provide(taskJob.taskJobRuntimeKey, active); return () => h(Page) } }) }
  }

  return defineAdminModule({
    key: TASK_JOB_MODULE_KEY,
    routes: [{
      name: TASK_JOB_ROUTE_NAME,
      path: TASK_JOB_ROUTE_PATH,
      component: loadTaskJobRoute,
      access: { moduleKey: TASK_JOB_MODULE_KEY, permissionKeys: [TASK_JOB_READ_PERMISSION] },
    }],
    disposeOnTenantChange: true,
    stores: [{ key: TASK_JOB_STORE_KEY, dispose() { runtime?.dispose(); runtime = null } }],
  })
}

export const peanutTaskJobModule = createPeanutTaskJobModule({ client: UNCONFIGURED_TENANT_CLIENT })
