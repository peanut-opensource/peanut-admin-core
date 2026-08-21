import {
  createReferenceCodesFetchTransport,
  createReferenceCodesModuleContribution,
  createReferenceCodesRuntime,
} from '@peanut-admin/admin/reference-codes'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'
import type { ReferenceCodesRuntime } from '@peanut-admin/admin/reference-codes'

export interface PeanutReferenceCodesHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
  canManage: () => boolean
}

export interface PeanutReferenceCodesHost {
  module: AdminModuleContribution
  runtime: ReferenceCodesRuntime
}

export const createPeanutReferenceCodesHost = (
  options: PeanutReferenceCodesHostOptions,
): PeanutReferenceCodesHost => {
  const runtime = createReferenceCodesRuntime({
    transport: createReferenceCodesFetchTransport({
      baseUrl: options.baseUrl,
      fetch: options.fetch,
    }),
    canRead: options.canRead,
    canManage: options.canManage,
  })

  return {
    module: createReferenceCodesModuleContribution(runtime),
    runtime,
  }
}
