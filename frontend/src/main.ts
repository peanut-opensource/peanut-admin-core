import 'element-plus/dist/index.css'
import {
  ElAlert,
  ElButton,
  ElDescriptions,
  ElDescriptionsItem,
  ElDialog,
  ElDrawer,
  ElForm,
  ElFormItem,
  ElInput,
  ElLoading,
  ElOption,
  ElPagination,
  ElRadio,
  ElRadioButton,
  ElRadioGroup,
  ElSelect,
  ElSkeleton,
  ElTable,
  ElTableColumn,
  ElTag,
} from 'element-plus'
import { createPinia, setActivePinia } from 'pinia'
import { createApp } from 'vue'
import type { Component } from 'vue'

import App from './App.vue'
import { readAdminHostConfig } from './app/host-config'
import { createAdminRouter } from './app/router'
import { createAdminRuntime, installAdminRuntime } from './app/runtime'
import './style.css'

const pinia = createPinia()
setActivePinia(pinia)
const runtime = createAdminRuntime(readAdminHostConfig())
const router = createAdminRouter(runtime)
installAdminRuntime(runtime)

const app = createApp(App)
const elementComponents: Readonly<Record<string, Component>> = {
  ElAlert: ElAlert as unknown as Component,
  ElButton: ElButton as unknown as Component,
  ElDescriptions: ElDescriptions as unknown as Component,
  ElDescriptionsItem: ElDescriptionsItem as unknown as Component,
  ElDialog: ElDialog as unknown as Component,
  ElDrawer: ElDrawer as unknown as Component,
  ElForm: ElForm as unknown as Component,
  ElFormItem: ElFormItem as unknown as Component,
  ElInput: ElInput as unknown as Component,
  ElOption: ElOption as unknown as Component,
  ElPagination: ElPagination as unknown as Component,
  ElRadio: ElRadio as unknown as Component,
  ElRadioButton: ElRadioButton as unknown as Component,
  ElRadioGroup: ElRadioGroup as unknown as Component,
  ElSelect: ElSelect as unknown as Component,
  ElSkeleton: ElSkeleton as unknown as Component,
  ElTable: ElTable as unknown as Component,
  ElTableColumn: ElTableColumn as unknown as Component,
  ElTag: ElTag as unknown as Component,
}
for (const [name, component] of Object.entries(elementComponents)) app.component(name, component)

app
  .use(pinia)
  .use(router)
  .directive('loading', ElLoading.directive)
  .mount('#app')
