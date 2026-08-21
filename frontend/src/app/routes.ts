import { createAdminNavigationRegistry } from '@peanut-admin/admin/core'
import type { AdminNavigationRoute, ApiAudience } from '@peanut-admin/admin/core'

import { exampleReferenceModule } from '../modules/example-reference'
import { exampleTargetModule } from '../modules/example-target'
import { exampleWorkItemModule } from '../modules/example-work-item'
import { peanutReferenceCodesModule } from '../modules/peanut-reference-codes'
import { peanutFileMediaModule } from '../modules/peanut-file-media'
import { peanutSettingsModule } from '../modules/peanut-settings'
import { peanutTaskJobModule } from '../modules/peanut-task-job'
import { peanutNotificationSmsModule } from '../modules/peanut-notification-sms'
import { peanutImportExportModule } from '../modules/peanut-import-export'
import { peanutIntegrationSecurityModule } from '../modules/peanut-integration-security'

export interface AppRouteRegistration {
  name: string
  path: string
  audience: ApiAudience
  permission?: string
  moduleKey?: string
}

export interface TrustedMenuRouteContract {
  componentKey: string
  clientKeys: readonly string[]
}

const registrations: readonly AppRouteRegistration[] = [
  { name: 'tenant.home', path: '/app', audience: 'tenant' },
  { name: 'tenant.account', path: '/app/account', audience: 'tenant' },
  { name: 'tenant.members.list', path: '/app/members', audience: 'tenant', permission: 'core.member.read' },
  { name: 'tenant.members.effective-access', path: '/app/members/:member_id/effective-access', audience: 'tenant', permission: 'core.member.effective-access.read' },
  { name: 'tenant.departments.list', path: '/app/departments', audience: 'tenant', permission: 'core.department.read' },
  { name: 'tenant.roles.list', path: '/app/roles', audience: 'tenant', permission: 'core.role.read' },
  { name: 'tenant.governance.workbench', path: '/app/governance', audience: 'tenant', permission: 'core.role.read' },
  { name: 'tenant.modules.list', path: '/app/modules', audience: 'tenant', permission: 'core.module.read' },
  { name: 'tenant.audit.list', path: '/app/audit', audience: 'tenant', permission: 'core.audit.read' },
  { name: 'platform.home', path: '/platform', audience: 'platform' },
  { name: 'platform.tenants.list', path: '/platform/tenants', audience: 'platform', permission: 'platform.tenant.read' },
  { name: 'platform.tenants.detail', path: '/platform/tenants/:tenant_id', audience: 'platform', permission: 'platform.tenant.read' },
  { name: 'platform.operators.list', path: '/platform/operators', audience: 'platform', permission: 'platform.operator.read' },
  { name: 'platform.roles.list', path: '/platform/roles', audience: 'platform', permission: 'platform.role.read' },
  { name: 'platform.governance.workbench', path: '/platform/governance', audience: 'platform', permission: 'platform.role.read' },
  { name: 'platform.audit.list', path: '/platform/audit', audience: 'platform', permission: 'platform.audit.read' },
  { name: 'platform.upgrade.status', path: '/platform/upgrade', audience: 'platform', permission: 'platform.upgrade.read' },
  { name: 'platform.ops.console', path: '/platform/ops', audience: 'platform', permission: 'platform.ops.read' },
]

export const APP_ROUTE_REGISTRATIONS = registrations

export const TRUSTED_MENU_ROUTE_CONTRACTS: Readonly<Record<string, TrustedMenuRouteContract>> = {
  'tenant.members.list': { componentKey: 'core.member.list', clientKeys: ['admin-web'] },
  'tenant.departments.list': { componentKey: 'core.department.list', clientKeys: ['admin-web'] },
  'tenant.roles.list': { componentKey: 'core.role.list', clientKeys: ['admin-web'] },
  'tenant.governance.workbench': { componentKey: 'core.governance.workbench', clientKeys: ['admin-web'] },
  'tenant.modules.list': { componentKey: 'core.module.list', clientKeys: ['admin-web'] },
  'tenant.audit.list': { componentKey: 'core.audit.list', clientKeys: ['admin-web'] },
  'platform.tenants.list': { componentKey: 'platform.tenant.list', clientKeys: ['platform-web'] },
  'platform.operators.list': { componentKey: 'platform.operator.list', clientKeys: ['platform-web'] },
  'platform.roles.list': { componentKey: 'platform.role.list', clientKeys: ['platform-web'] },
  'platform.governance.workbench': { componentKey: 'platform.governance.workbench', clientKeys: ['platform-web'] },
  'platform.audit.list': { componentKey: 'platform.audit.list', clientKeys: ['platform-web'] },
  'platform.upgrade.status': { componentKey: 'platform.upgrade.status', clientKeys: ['platform-web'] },
  'platform.ops.console': { componentKey: 'platform.ops.console', clientKeys: ['platform-web'] },
  'peanut.settings.list': { componentKey: 'peanut.settings.page', clientKeys: ['admin-web'] },
  'peanut.reference-codes.list': { componentKey: 'peanut.reference-codes.page', clientKeys: ['admin-web'] },
  'peanut.file-media.list': { componentKey: 'peanut.file-media.page', clientKeys: ['admin-web'] },
  'peanut.task-job.list': { componentKey: 'peanut.task-job.page', clientKeys: ['admin-web'] },
  'peanut.notification-sms.inbox': { componentKey: 'peanut.notification-sms.page', clientKeys: ['admin-web'] },
  'peanut.import-export.list': { componentKey: 'peanut.import-export.page', clientKeys: ['admin-web'] },
  'peanut.integration-security.index': { componentKey: 'peanut.integration-security.page', clientKeys: ['admin-web'] },
  'example-target-list': { componentKey: 'example.target.list', clientKeys: ['admin-web'] },
  'example-reference-list': { componentKey: 'example.reference.list', clientKeys: ['admin-web'] },
  'example-work-item-list': { componentKey: 'example.work-item.list', clientKeys: ['admin-web'] },
  'example-work-item-policy': { componentKey: 'example.work-item.policy', clientKeys: ['admin-web'] },
}

export const APP_MODULES = [
  exampleTargetModule,
  exampleReferenceModule,
  exampleWorkItemModule,
  peanutSettingsModule,
  peanutReferenceCodesModule,
  peanutFileMediaModule,
  peanutTaskJobModule,
  peanutNotificationSmsModule,
  peanutImportExportModule,
  peanutIntegrationSecurityModule,
] as const
export const APP_NAVIGATION = createAdminNavigationRegistry({ routes: registrations, modules: APP_MODULES })
export const APP_ROUTE_REGISTRY = new Map<string, AdminNavigationRoute>(
  APP_NAVIGATION.routes().map(route => [route.name, route]),
)

export const audienceForPath = (path: string): ApiAudience | null => {
  const pathname = new URL(path, 'https://peanut-admin.test').pathname
  if (pathname === '/app' || pathname.startsWith('/app/')) return 'tenant'
  if (pathname === '/platform' || pathname.startsWith('/platform/')) return 'platform'
  return null
}

export const safeReturnTo = (value: unknown, audience: ApiAudience): string => {
  const fallback = audience === 'tenant' ? '/app' : '/platform'
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) {
    return fallback
  }
  const url = new URL(value, 'https://peanut-admin.test')
  if (url.origin !== 'https://peanut-admin.test' || audienceForPath(url.pathname) !== audience) {
    return fallback
  }
  if (url.pathname === '/platform/login') {
    return fallback
  }

  return `${url.pathname}${url.search}${url.hash}`
}

export const resolveMenuDestination = (menu: {
  route_name?: unknown
  route_path?: unknown
  component?: unknown
}): string | null => {
  return APP_NAVIGATION.resolveMenu(menu)?.path ?? null
}
