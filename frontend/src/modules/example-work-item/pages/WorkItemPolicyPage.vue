<script setup lang="ts">
import { EmptyState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar, TargetScopeSummary, TargetSelector } from '@peanut-admin/admin/shell'
import type { TargetCandidate, TypedTarget } from '@peanut-admin/admin/core'
import { onMounted, ref } from 'vue'

import { apiCollection } from '../../../app/contracts'
import type { UnknownRecord } from '../../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../../app/runtime'
import { loadTargetCandidatePage } from '../../../components/targets/candidates'

const runtime = useAdminRuntime()
const candidates = ref<TargetCandidate[]>([])
const selected = ref<TypedTarget[]>([])
const results = ref<UnknownRecord[]>([])
const loading = ref(false)
const publishing = ref(false)
const problem = ref<AdminApiError | null>(null)

const load = async () => {
  loading.value = true
  problem.value = null
  try {
    const page = await loadTargetCandidatePage(runtime, {
      resourceKey: 'example.work-item',
      operation: 'policy-publish',
      targetResourceKey: 'example.project',
      targetRole: 'primary',
      mode: 'policy-config',
    })
    candidates.value = page.candidates
    selected.value = []
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

const publish = async () => {
  if (selected.value.length === 0) return
  publishing.value = true
  problem.value = null
  try {
    const response = runtime.unwrap(await runtime.tenantClient.POST('/api/v1/example/work-item-view-policies', {
      params: { header: { 'Idempotency-Key': globalThis.crypto.randomUUID() } },
      body: {
        name: 'Admin shell view policy',
        config: { reason: 'Published from the reference admin shell' },
        targets: [{
          target_resource_key: 'example.project',
          target_role: 'primary',
          target_ids: selected.value.map(target => target.target_id),
        }],
      },
    }))
    results.value = apiCollection(response).items
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    publishing.value = false
  }
}

onMounted(load)
</script>

<template>
  <PageContent>
    <PageHeader>目标策略发布</PageHeader>
    <PageToolbar label="发布目标">
      <TargetSelector
        v-model="selected"
        :candidates="candidates"
        multiple
        :loading="loading"
      />
      <el-button
        type="primary"
        :loading="publishing"
        :disabled="selected.length === 0"
        @click="publish"
      >
        发布到所选目标
      </el-button>
      <el-button
        :loading="loading"
        @click="load"
      >
        刷新候选
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
        :mode="candidates.length === 0 ? 'zero' : 'multiple'"
        :available-count="candidates.length"
        :selected-count="selected.length"
      />
      <el-table
        v-if="results.length > 0"
        :data="results"
        class="resource-table"
      >
        <el-table-column
          prop="target_label"
          label="目标"
          min-width="220"
        />
        <el-table-column
          prop="status"
          label="执行状态"
          width="140"
        />
        <el-table-column
          prop="message"
          label="结果"
          min-width="240"
        />
      </el-table>
      <EmptyState
        v-else-if="candidates.length === 0"
        title="没有可发布目标"
        message="当前角色没有可委派的目标候选。"
      />
      <EmptyState
        v-else
        title="尚未发布"
        message="选择目标后执行策略发布。"
      />
    </template>
  </PageContent>
</template>
