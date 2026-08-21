import type { TargetCandidate } from '@peanut-admin/admin/core'

import { apiCollection, targetCandidates } from '../../app/contracts'
import type { AdminRuntime } from '../../app/runtime'

export interface TargetCandidateRequest {
  resourceKey: string
  operation: string
  targetResourceKey: string
  targetRole: string
  mode?: 'runtime' | 'policy-config'
  page?: number
  pageSize?: number
}

export interface TargetCandidatePage {
  candidates: TargetCandidate[]
  availableCount: number
  total: number
  mode: 'zero' | 'single' | 'multiple'
}

export const loadTargetCandidatePage = async (
  runtime: AdminRuntime,
  request: TargetCandidateRequest,
): Promise<TargetCandidatePage> => {
  const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/authorization/target-candidates', {
    params: {
      query: {
        resource_key: request.resourceKey,
        operation: request.operation,
        target_resource_key: request.targetResourceKey,
        target_role: request.targetRole,
        mode: request.mode ?? 'runtime',
        page: request.page ?? 1,
        page_size: request.pageSize ?? 20,
      },
    },
  }))
  const collection = apiCollection(response)
  const candidates = targetCandidates(response)
  const availableCount = typeof collection.meta.available_count === 'number'
    ? collection.meta.available_count
    : candidates.length
  const total = typeof collection.meta.total === 'number' ? collection.meta.total : availableCount

  return {
    candidates,
    availableCount,
    total,
    mode: availableCount === 0 ? 'zero' : (availableCount === 1 ? 'single' : 'multiple'),
  }
}
