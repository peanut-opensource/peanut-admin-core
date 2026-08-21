<script setup lang="ts">
import { EmptyState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar, TargetScopeSummary, TargetSelector } from '@peanut-admin/admin/shell'
import { computed, onMounted, ref, watch } from 'vue'
import type { TargetCandidate, TypedTarget } from '@peanut-admin/admin/core'

import { apiCollection } from '../../../app/contracts'
import type { UnknownRecord } from '../../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../../app/runtime'
import { loadTargetCandidatePage } from '../../../components/targets/candidates'

const runtime = useAdminRuntime()
const candidates = ref<TargetCandidate[]>([])
const selected = ref<TypedTarget[]>([])
const rows = ref<UnknownRecord[]>([])
const loading = ref(false)
const problem = ref<AdminApiError | null>(null)
const scopeMode = computed(() => candidates.value.length === 0 ? 'zero' : (candidates.value.length === 1 ? 'single' : 'multiple'))

const loadReferences = async () => {
  const target = selected.value[0]
  if (target === undefined) {
    rows.value = []
    return
  }
  loading.value = true
  problem.value = null
  try {
    const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/example/reference-items/candidates', {
      params: { query: { target_resource_key: target.target_resource_key, target_role: target.target_role, target_id: target.target_id } },
    }))
    rows.value = apiCollection(response).items
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

const initialize = async () => {
  loading.value = true
  problem.value = null
  try {
    const page = await loadTargetCandidatePage(runtime, {
      resourceKey: 'example.reference-item',
      operation: 'use',
      targetResourceKey: 'example.project',
      targetRole: 'primary',
    })
    candidates.value = page.candidates
    selected.value = page.candidates.length === 1
      ? [{
          target_resource_key: page.candidates[0]!.target_resource_key,
          target_role: page.candidates[0]!.target_role,
          target_id: page.candidates[0]!.target_id,
        }]
      : []
    await loadReferences()
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

watch(selected, loadReferences, { deep: true })
onMounted(initialize)
</script>

<template>
  <PageContent>
    <PageHeader>统一共享主档</PageHeader>
    <PageToolbar label="目标选择">
      <TargetSelector
        v-if="scopeMode === 'multiple'"
        v-model="selected"
        :candidates="candidates"
        :multiple="false"
        :loading="loading"
      />
      <el-button
        :loading="loading"
        @click="initialize"
      >
        刷新
      </el-button>
    </PageToolbar>
    <ModuleUnavailableState
      v-if="problem"
      :message="problem.problem.detail"
      :request-id="problem.problem.request_id"
      @action="initialize"
    />
    <template v-else>
      <TargetScopeSummary
        :mode="scopeMode"
        :available-count="candidates.length"
        :selected-count="selected.length"
      />
      <el-table
        v-if="rows.length > 0"
        :data="rows"
        class="resource-table"
      >
        <el-table-column
          prop="name"
          label="主档名称"
          min-width="220"
        />
        <el-table-column
          prop="code"
          label="代码"
          min-width="160"
        />
        <el-table-column
          prop="owner_type"
          label="归属"
          width="130"
        >
          <template #default="scope">
            <el-tag effect="plain">
              {{ scope.row.owner_type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          prop="status"
          label="状态"
          width="120"
        />
      </el-table>
      <EmptyState
        v-else-if="scopeMode === 'zero'"
        title="没有可用目标"
        message="当前没有可引用共享主档的目标。"
      />
      <EmptyState
        v-else-if="selected.length === 0"
        title="请选择目标"
        message="选择一个目标后加载统一主档候选。"
      />
      <EmptyState
        v-else-if="!loading"
        title="暂无主档"
        message="当前目标没有可用的统一主档。"
      />
    </template>
  </PageContent>
</template>
