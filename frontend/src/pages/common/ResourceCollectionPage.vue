<script setup lang="ts">
import { hasPermission, useTenantContext } from '@peanut-admin/admin/core'
import { EmptyState, ForbiddenState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar } from '@peanut-admin/admin/shell'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import type { UnknownRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { RESOURCE_PAGES } from './resources'

const route = useRoute()
const runtime = useAdminRuntime()
const tenantContext = useTenantContext()
const definition = computed(() => RESOURCE_PAGES[stringPageKey.value] ?? null)
const stringPageKey = computed(() => typeof route.meta.resourcePage === 'string' ? route.meta.resourcePage : '')
const rows = ref<UnknownRecord[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const problem = ref<AdminApiError | null>(null)
const retryRemaining = ref(0)
const canPreviewEffectiveAccess = computed(() => (
  stringPageKey.value === 'tenant-members'
  && hasPermission(tenantContext.permissionSet, 'core.member.effective-access.read')
))
let retryTimer: ReturnType<typeof setInterval> | null = null

const clearRetryTimer = () => {
  if (retryTimer !== null) clearInterval(retryTimer)
  retryTimer = null
}

const startRetryTimer = (retryAfter: string | null) => {
  clearRetryTimer()
  const seconds = Number.parseInt(retryAfter ?? '0', 10)
  retryRemaining.value = Number.isFinite(seconds) && seconds > 0 ? seconds : 0
  if (retryRemaining.value === 0) return
  retryTimer = setInterval(() => {
    retryRemaining.value = Math.max(0, retryRemaining.value - 1)
    if (retryRemaining.value === 0) clearRetryTimer()
  }, 1000)
}

const load = async () => {
  if (definition.value === null) return
  if (retryRemaining.value > 0) return
  loading.value = true
  problem.value = null
  try {
    const result = await definition.value.load(runtime, page.value, pageSize.value)
    rows.value = result.items
    total.value = typeof result.meta.total === 'number' ? result.meta.total : result.items.length
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
    if (problem.value?.problem.status === 429) startRetryTimer(problem.value.retryAfter)
    rows.value = []
  } finally {
    loading.value = false
  }
}

const displayValue = (row: UnknownRecord, key: string): string => {
  const value = row[key]
  if (value === null || value === undefined || value === '') return '-'
  if (Array.isArray(value)) return value.filter(item => typeof item === 'string').join('、') || '-'
  if (typeof value === 'string' || typeof value === 'number') return String(value)
  return '-'
}

watch(stringPageKey, () => {
  page.value = 1
  void load()
})
onMounted(load)
onUnmounted(clearRetryTimer)
</script>

<template>
  <PageContent v-if="definition">
    <PageHeader>{{ definition.title }}</PageHeader>
    <PageToolbar label="列表操作">
      <el-button
        :loading="loading"
        :disabled="retryRemaining > 0"
        @click="load"
      >
        {{ retryRemaining > 0 ? `${retryRemaining} 秒后重试` : '刷新' }}
      </el-button>
    </PageToolbar>

    <ForbiddenState
      v-if="problem?.problem.status === 403"
      :request-id="problem.problem.request_id"
    />
    <ModuleUnavailableState
      v-else-if="problem?.problem.status === 503"
      :message="problem.problem.detail"
      :request-id="problem.problem.request_id"
      @action="load"
    />
    <el-alert
      v-else-if="problem"
      :title="problem.problem.detail"
      type="error"
      :closable="false"
    >
      <template #default>
        请求编号：{{ problem.problem.request_id }}
        <span v-if="retryRemaining > 0">，请在 {{ retryRemaining }} 秒后重试。</span>
      </template>
    </el-alert>
    <el-table
      v-else-if="rows.length > 0 || loading"
      v-loading="loading"
      :data="rows"
      class="resource-table"
      table-layout="fixed"
    >
      <el-table-column
        v-for="column in definition.columns"
        :key="column.key"
        :label="column.label"
        :min-width="column.minWidth"
      >
        <template #default="scope">
          <el-tag
            v-if="column.key === 'status'"
            size="small"
            effect="plain"
          >
            {{ displayValue(scope.row, column.key) }}
          </el-tag>
          <span v-else>{{ displayValue(scope.row, column.key) }}</span>
        </template>
      </el-table-column>
      <el-table-column
        v-if="stringPageKey === 'platform-tenants' || canPreviewEffectiveAccess"
        label="操作"
        width="112"
        fixed="right"
      >
        <template #default="scope">
          <RouterLink
            v-if="stringPageKey === 'platform-tenants'"
            :to="`/platform/tenants/${String(scope.row.id)}`"
          >
            查看
          </RouterLink>
          <RouterLink
            v-else
            :to="`/app/members/${String(scope.row.id)}/effective-access`"
          >
            有效访问
          </RouterLink>
        </template>
      </el-table-column>
    </el-table>
    <EmptyState
      v-else
      title="暂无数据"
      message="当前列表没有可显示的数据。"
    />
    <el-pagination
      v-if="total > pageSize"
      v-model:current-page="page"
      v-model:page-size="pageSize"
      :total="total"
      :page-sizes="[20, 50, 100]"
      layout="total, sizes, prev, pager, next"
      class="page-pagination"
      @change="load"
    />
  </PageContent>
</template>
