<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import type { Component } from 'vue'
import type { ApiAudience } from '@peanut-admin/admin/core'
import type { ShellBreadcrumbItem, ShellNavigationItem } from '@peanut-admin/admin/shell'

import type { AdminMenuItem } from '../app/contracts'
import { useAdminRuntime } from '../app/runtime'
import { useWorkspaceStore } from '../app/store'
import { readShellHostConfig } from './host-config'

const route = useRoute()
const router = useRouter()
const runtime = useAdminRuntime()
const workspace = useWorkspaceStore()
const shellConfig = readShellHostConfig(import.meta.env)
const audience = computed<ApiAudience>(() => route.meta.audience ?? 'tenant')
const shellComponent = computed<Component>(() => runtime.workspaceShell(audience.value))
const identity = computed(() => audience.value === 'tenant' ? workspace.tenantIdentity : workspace.platformIdentity)
const title = computed(() => typeof route.meta.title === 'string' ? route.meta.title : '工作台')

const convertMenu = (menu: AdminMenuItem): ShellNavigationItem | null => {
  const children = menu.children.map(convertMenu).filter((item): item is ShellNavigationItem => item !== null)
  if (menu.type === 'group') return { key: menu.key, label: menu.name, path: null, children }
  if (menu.route_name === null) return null
  const registration = runtime.routeRegistry.get(menu.route_name)
  if (registration === undefined || registration.audience !== audience.value) {
    workspace.addMenuDiagnostic(menu.route_name)
    return null
  }
  return { key: menu.key, label: menu.name, path: registration.path, children }
}

const navigation = computed<ShellNavigationItem[]>(() => {
  const home: ShellNavigationItem = {
    key: `${audience.value}.home`,
    label: '工作台',
    path: audience.value === 'tenant' ? '/app' : '/platform',
    children: [],
  }
  const account: ShellNavigationItem[] = audience.value === 'tenant'
    ? [{ key: 'tenant.account', label: '账号信息', path: '/app/account', children: [] }]
    : []
  const menus = audience.value === 'tenant' ? workspace.tenantMenus : workspace.platformMenus
  return [home, ...account, ...menus.map(convertMenu).filter((item): item is ShellNavigationItem => item !== null)]
})

const breadcrumbs = computed<ShellBreadcrumbItem[]>(() => [{
  label: shellConfig.audiences[audience.value].label,
  path: audience.value === 'tenant' ? '/app' : '/platform',
}, {
  label: title.value,
  path: null,
}])

const navigate = async (path: string) => {
  await router.push(path)
}

const switchTenant = async () => {
  await runtime.beginTenantSwitch()
  await router.push({ name: 'tenant.select', query: { return_to: '/app' } })
}

const logout = async () => {
  const currentAudience = audience.value
  await runtime.logout(currentAudience)
  await router.replace(currentAudience === 'tenant' ? '/login' : '/platform/login')
}

</script>

<template>
  <component
    :is="shellComponent"
    v-model:collapsed="workspace.shellCollapsed"
    v-model:mobile-open="workspace.mobileNavigationOpen"
    :config="shellConfig"
    :identity="identity"
    :navigation="navigation"
    :breadcrumbs="breadcrumbs"
    :active-path="route.path"
    open-navigation-label="打开导航"
    collapse-navigation-label="收起导航"
    expand-navigation-label="展开导航"
    primary-navigation-label="主导航"
    mobile-navigation-label="移动导航"
    breadcrumb-label="当前位置"
    identity-label="当前身份"
    @navigate="navigate"
    @switch-tenant="switchTenant"
    @logout="logout"
  >
    <RouterView />
  </component>
</template>
