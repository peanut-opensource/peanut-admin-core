import { parseProblemDetails } from '@peanut-admin/admin/core'
import type { ProblemDetails, TargetCandidate } from '@peanut-admin/admin/core'

export type UnknownRecord = Record<string, unknown>

export const isRecord = (value: unknown): value is UnknownRecord => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
)

export const stringValue = (value: unknown, fallback = ''): string => (
  typeof value === 'string' ? value : fallback
)

export const stringArray = (value: unknown): string[] => (
  Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []
)

export const envelopeData = (value: unknown): unknown => (
  isRecord(value) && 'data' in value ? value.data : value
)

export interface ApiCollection {
  items: UnknownRecord[]
  meta: UnknownRecord
}

export const apiCollection = (value: unknown): ApiCollection => {
  if (!isRecord(value)) return { items: [], meta: {} }
  const data = value.data
  return {
    items: Array.isArray(data) ? data.filter(isRecord) : [],
    meta: isRecord(value.meta) ? value.meta : {},
  }
}

export interface AdminMenuItem {
  key: string
  type: 'group' | 'page' | 'link'
  name: string
  route_name: string | null
  icon: string | null
  children: AdminMenuItem[]
}

const menuItem = (value: unknown): AdminMenuItem | null => {
  if (!isRecord(value) || typeof value.key !== 'string' || typeof value.name !== 'string') return null
  const type = value.type === 'group' || value.type === 'link' ? value.type : 'page'
  return {
    key: value.key,
    type,
    name: value.name,
    route_name: typeof value.route_name === 'string' ? value.route_name : null,
    icon: typeof value.icon === 'string' ? value.icon : null,
    children: Array.isArray(value.children)
      ? value.children.map(menuItem).filter((item): item is AdminMenuItem => item !== null)
      : [],
  }
}

export const menuItems = (value: unknown): AdminMenuItem[] => {
  const data = envelopeData(value)
  return Array.isArray(data)
    ? data.map(menuItem).filter((item): item is AdminMenuItem => item !== null)
    : []
}

export interface TenantChoice {
  tenantId: string
  tenantCode: string
  tenantName: string
  memberId: string
  memberDisplayName: string
}

export interface TenantLoginSelection {
  state: 'tenant_selection_required'
  challengeToken: string
  expiresAt: string
  tenants: TenantChoice[]
}

export interface AuthenticatedLogin {
  state: 'authenticated'
  accessToken: string
}

export type TenantLoginResult = TenantLoginSelection | AuthenticatedLogin

export const tenantLoginResult = (value: unknown): TenantLoginResult => {
  const data = envelopeData(value)
  if (!isRecord(data)) throw new Error('AUTH_RESPONSE_INVALID')
  if (data.state === 'authenticated' && typeof data.access_token === 'string') {
    return { state: 'authenticated', accessToken: data.access_token }
  }
  if (data.state !== 'tenant_selection_required'
    || typeof data.challenge_token !== 'string'
    || !Array.isArray(data.tenants)) {
    throw new Error('AUTH_RESPONSE_INVALID')
  }
  const tenants = data.tenants.flatMap(item => {
    if (!isRecord(item) || typeof item.tenant_id !== 'string') return []
    return [{
      tenantId: item.tenant_id,
      tenantCode: stringValue(item.tenant_code),
      tenantName: stringValue(item.tenant_name, item.tenant_id),
      memberId: stringValue(item.tenant_member_id),
      memberDisplayName: stringValue(item.member_display_name),
    }]
  })
  if (tenants.length === 0) throw new Error('AUTH_RESPONSE_INVALID')

  return {
    state: 'tenant_selection_required',
    challengeToken: data.challenge_token,
    expiresAt: stringValue(data.expires_at),
    tenants,
  }
}

export const authenticatedLogin = (value: unknown): AuthenticatedLogin => {
  const result = tenantLoginResult(value)
  if (result.state !== 'authenticated') throw new Error('AUTH_RESPONSE_INVALID')
  return result
}

export const problemFromResponse = (
  value: unknown,
  response: Response,
): ProblemDetails => parseProblemDetails(value) ?? {
  type: '/docs/problems/request-failed',
  title: 'Request failed',
  status: response.status >= 400 ? response.status : 500,
  detail: 'The request could not be completed.',
  code: response.status === 503 ? 'SERVICE_UNAVAILABLE' : 'REQUEST_FAILED',
  request_id: response.headers.get('X-Request-Id') ?? 'req_unavailable',
}

export const targetCandidates = (value: unknown): TargetCandidate[] => {
  const { items } = apiCollection(value)
  return items.flatMap(item => {
    if (typeof item.target_resource_key !== 'string'
      || typeof item.target_role !== 'string'
      || typeof item.target_id !== 'string'
      || typeof item.label !== 'string') return []
    return [{
      target_resource_key: item.target_resource_key,
      target_role: item.target_role,
      target_id: item.target_id,
      label: item.label,
      ...(typeof item.owner_label === 'string' ? { owner_label: item.owner_label } : {}),
      ...(typeof item.status === 'string' ? { status: item.status } : {}),
    }]
  })
}
