import type { ApiAudience } from '@peanut-admin/admin/core'

import { apiCollection } from '../../app/contracts'
import type { ApiCollection } from '../../app/contracts'
import type { AdminRuntime } from '../../app/runtime'

export interface ResourceColumn {
  key: string
  label: string
  minWidth: number
}

export interface ResourcePageDefinition {
  title: string
  audience: ApiAudience
  columns: readonly ResourceColumn[]
  load: (runtime: AdminRuntime, page: number, pageSize: number) => Promise<ApiCollection>
}

const tenantColumns = {
  members: [
    { key: 'display_name', label: '成员', minWidth: 160 },
    { key: 'member_no', label: '成员编号', minWidth: 120 },
    { key: 'status', label: '状态', minWidth: 100 },
    { key: 'role_keys', label: '角色', minWidth: 180 },
  ],
  departments: [
    { key: 'name', label: '部门', minWidth: 180 },
    { key: 'code', label: '代码', minWidth: 140 },
    { key: 'parent_id', label: '上级部门', minWidth: 120 },
    { key: 'status', label: '状态', minWidth: 100 },
  ],
  roles: [
    { key: 'name', label: '角色', minWidth: 180 },
    { key: 'key', label: '角色标识', minWidth: 180 },
    { key: 'status', label: '状态', minWidth: 100 },
    { key: 'permission_count', label: '权限数', minWidth: 100 },
  ],
  modules: [
    { key: 'name', label: '模块', minWidth: 180 },
    { key: 'module_key', label: '模块标识', minWidth: 200 },
    { key: 'status', label: '状态', minWidth: 100 },
    { key: 'version', label: '版本', minWidth: 120 },
  ],
  audit: [
    { key: 'created_at', label: '时间', minWidth: 180 },
    { key: 'event_type', label: '事件', minWidth: 200 },
    { key: 'actor_label', label: '操作人', minWidth: 150 },
    { key: 'request_id', label: '请求编号', minWidth: 220 },
  ],
} as const

const platformColumns = {
  tenants: [
    { key: 'display_name', label: '租户', minWidth: 200 },
    { key: 'code', label: '租户代码', minWidth: 160 },
    { key: 'status', label: '状态', minWidth: 110 },
    { key: 'created_at', label: '创建时间', minWidth: 180 },
  ],
  operators: [
    { key: 'display_name', label: '操作员', minWidth: 180 },
    { key: 'email', label: '邮箱', minWidth: 220 },
    { key: 'status', label: '状态', minWidth: 100 },
    { key: 'role_keys', label: '角色', minWidth: 180 },
  ],
  roles: [
    { key: 'name', label: '平台角色', minWidth: 180 },
    { key: 'key', label: '角色标识', minWidth: 200 },
    { key: 'status', label: '状态', minWidth: 100 },
    { key: 'permission_count', label: '权限数', minWidth: 100 },
  ],
  audit: [
    { key: 'created_at', label: '时间', minWidth: 180 },
    { key: 'event_type', label: '平台事件', minWidth: 220 },
    { key: 'operator_label', label: '操作员', minWidth: 160 },
    { key: 'target_tenant_id', label: '目标租户', minWidth: 130 },
  ],
} as const

export const RESOURCE_PAGES: Readonly<Record<string, ResourcePageDefinition>> = {
  'tenant-members': {
    title: '成员管理', audience: 'tenant', columns: tenantColumns.members,
    load: async (runtime, page, pageSize) => apiCollection(runtime.unwrap(await runtime.tenantClient.GET('/api/v1/members', { params: { query: { page, page_size: pageSize } } }))),
  },
  'tenant-departments': {
    title: '部门管理', audience: 'tenant', columns: tenantColumns.departments,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.tenantClient.GET('/api/v1/departments'))),
  },
  'tenant-roles': {
    title: '角色管理', audience: 'tenant', columns: tenantColumns.roles,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.tenantClient.GET('/api/v1/roles'))),
  },
  'tenant-modules': {
    title: '模块管理', audience: 'tenant', columns: tenantColumns.modules,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.tenantClient.GET('/api/v1/modules'))),
  },
  'tenant-audit': {
    title: '审计日志', audience: 'tenant', columns: tenantColumns.audit,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.tenantClient.GET('/api/v1/audit-events'))),
  },
  'platform-tenants': {
    title: '租户管理', audience: 'platform', columns: platformColumns.tenants,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/tenants'))),
  },
  'platform-operators': {
    title: '平台操作员', audience: 'platform', columns: platformColumns.operators,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/operators'))),
  },
  'platform-roles': {
    title: '平台角色', audience: 'platform', columns: platformColumns.roles,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/roles'))),
  },
  'platform-audit': {
    title: '平台审计', audience: 'platform', columns: platformColumns.audit,
    load: async runtime => apiCollection(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/audit-events'))),
  },
}
