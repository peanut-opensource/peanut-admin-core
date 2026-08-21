<script setup lang="ts">
import { EmptyState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar } from '@peanut-admin/admin/shell'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { envelopeData, isRecord } from '../../app/contracts'
import type { UnknownRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'

const route = useRoute()
const router = useRouter()
const runtime = useAdminRuntime()
const tenant = ref<UnknownRecord | null>(null)
const loading = ref(false)
const problem = ref<AdminApiError | null>(null)

const load = async () => {
  const tenantId = typeof route.params.tenant_id === 'string' ? route.params.tenant_id : ''
  if (tenantId === '') return
  loading.value = true
  problem.value = null
  try {
    const data = envelopeData(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/tenants/{tenant_id}', {
      params: { path: { tenant_id: tenantId } },
    })))
    tenant.value = isRecord(data) ? data : null
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <PageContent>
    <PageHeader>租户详情</PageHeader>
    <PageToolbar>
      <el-button @click="router.push('/platform/tenants')">
        返回列表
      </el-button>
    </PageToolbar>
    <ModuleUnavailableState
      v-if="problem?.problem.status === 404"
      title="租户不可用"
      message="未找到可显示的租户信息。"
      :request-id="problem.problem.request_id"
      @action="load"
    />
    <ModuleUnavailableState
      v-else-if="problem"
      :message="problem.problem.detail"
      :request-id="problem.problem.request_id"
      @action="load"
    />
    <el-skeleton
      v-else-if="loading"
      :rows="6"
      animated
    />
    <el-descriptions
      v-else-if="tenant"
      :column="2"
      border
      class="detail-list"
    >
      <el-descriptions-item label="租户">
        {{ tenant.display_name ?? tenant.name }}
      </el-descriptions-item>
      <el-descriptions-item label="租户代码">
        {{ tenant.code }}
      </el-descriptions-item>
      <el-descriptions-item label="状态">
        {{ tenant.status }}
      </el-descriptions-item>
      <el-descriptions-item label="时区">
        {{ tenant.timezone }}
      </el-descriptions-item>
      <el-descriptions-item label="语言">
        {{ tenant.locale }}
      </el-descriptions-item>
      <el-descriptions-item label="修订版本">
        {{ tenant.revision }}
      </el-descriptions-item>
    </el-descriptions>
    <EmptyState
      v-else
      title="租户不存在"
      message="未找到可显示的租户信息。"
    />
  </PageContent>
</template>
