<script setup lang="ts">
import { EmptyState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar, TargetScopeSummary } from '@peanut-admin/admin/shell'
import { onMounted, ref, watch } from 'vue'
import type { TargetCandidate } from '@peanut-admin/admin/core'

import { AdminApiError, useAdminRuntime } from '../../../app/runtime'
import { loadTargetCandidatePage } from '../../../components/targets/candidates'

const runtime = useAdminRuntime()
const targetType = ref<'example.project' | 'example.queue'>('example.project')
const rows = ref<TargetCandidate[]>([])
const loading = ref(false)
const problem = ref<AdminApiError | null>(null)

const load = async () => {
  loading.value = true
  problem.value = null
  try {
    const result = await loadTargetCandidatePage(runtime, {
      resourceKey: targetType.value,
      operation: 'select',
      targetResourceKey: targetType.value,
      targetRole: 'primary',
    })
    rows.value = result.candidates
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
    rows.value = []
  } finally {
    loading.value = false
  }
}

watch(targetType, load)
onMounted(load)
</script>

<template>
  <PageContent>
    <PageHeader>示例目标</PageHeader>
    <PageToolbar label="目标类别">
      <el-radio-group
        v-model="targetType"
        size="small"
      >
        <el-radio-button value="example.project">
          Project
        </el-radio-button>
        <el-radio-button value="example.queue">
          Queue
        </el-radio-button>
      </el-radio-group>
      <el-button
        :loading="loading"
        @click="load"
      >
        刷新
      </el-button>
    </PageToolbar>
    <ModuleUnavailableState
      v-if="problem"
      :message="problem.problem.detail"
      :request-id="problem.problem.request_id"
      @action="load"
    />
    <template v-else>
      <TargetScopeSummary
        :mode="rows.length === 0 ? 'zero' : (rows.length === 1 ? 'single' : 'multiple')"
        :available-count="rows.length"
        :selected-count="0"
      />
      <el-table
        v-if="rows.length > 0"
        v-loading="loading"
        :data="rows"
        class="resource-table"
      >
        <el-table-column
          prop="label"
          label="目标"
          min-width="220"
        />
        <el-table-column
          prop="target_resource_key"
          label="目标类别"
          min-width="180"
        />
        <el-table-column
          prop="status"
          label="状态"
          width="120"
        />
      </el-table>
      <EmptyState
        v-else-if="!loading"
        title="没有可用目标"
        message="当前操作没有可授权的目标。"
      />
    </template>
  </PageContent>
</template>
