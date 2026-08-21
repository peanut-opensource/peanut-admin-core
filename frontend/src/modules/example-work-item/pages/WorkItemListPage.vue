<script setup lang="ts">
import { EmptyState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar, TargetScopeSummary, TargetSelector } from '@peanut-admin/admin/shell'
import { hasPermission, useOperationTargets, useTenantContext } from '@peanut-admin/admin/core'
import type { OperationTargetScope, TargetCandidate, TypedTarget } from '@peanut-admin/admin/core'
import { computed, onMounted, reactive, ref, watch } from 'vue'

import { apiCollection, isRecord, stringValue } from '../../../app/contracts'
import type { UnknownRecord } from '../../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../../app/runtime'
import { loadTargetCandidatePage } from '../../../components/targets/candidates'

const runtime = useAdminRuntime()
const targetStore = useOperationTargets()
const tenantContext = useTenantContext()
const scope: OperationTargetScope = {
  moduleKey: 'example.work-item',
  resourceKey: 'example.work-item',
  operation: 'list',
  targetResourceKey: 'example.project',
  targetRole: 'primary',
  cardinality: 'many_readable',
}
const candidates = ref<TargetCandidate[]>([])
const rows = ref<UnknownRecord[]>([])
const aggregate = ref<UnknownRecord | null>(null)
const viewMode = ref<'list' | 'aggregate'>('list')
const loading = ref(false)
const problem = ref<AdminApiError | null>(null)
const createOpen = ref(false)
const createLoading = ref(false)
const createProblem = ref<AdminApiError | null>(null)
const createForm = reactive({ title: '', referenceItemId: '' })
const references = ref<UnknownRecord[]>([])
const selected = computed<TypedTarget[]>({
  get: () => {
    void targetStore.generation
    return targetStore.selected(scope)
  },
  set: value => targetStore.select(scope, value),
})
const scopeMode = computed(() => candidates.value.length === 0 ? 'zero' : (candidates.value.length === 1 ? 'single' : 'multiple'))
const canCreate = computed(() => hasPermission(tenantContext.permissionSet, 'example.work-item.create'))

const loadList = async () => {
  if (selected.value.length === 0) {
    rows.value = []
    return
  }
  loading.value = true
  problem.value = null
  try {
    const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/example/work-items', {
      params: {
        query: {
          page: 1,
          page_size: 20,
          target_resource_key: scope.targetResourceKey,
          target_role: scope.targetRole,
          target_id: selected.value.map(target => target.target_id),
          sort: '-created_at',
        },
      },
    }))
    rows.value = apiCollection(response).items
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

const loadAggregate = async () => {
  if (selected.value.length === 0) {
    aggregate.value = null
    return
  }
  loading.value = true
  problem.value = null
  try {
    const value = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/example/work-items/aggregate', {
      params: {
        query: {
          target_resource_key: scope.targetResourceKey,
          target_role: scope.targetRole,
          target_id: selected.value.map(target => target.target_id),
        },
      },
    }))
    const data = isRecord(value) && isRecord(value.data) ? value.data : value
    aggregate.value = isRecord(data) ? data : null
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
      resourceKey: 'example.work-item',
      operation: 'list',
      targetResourceKey: scope.targetResourceKey,
      targetRole: scope.targetRole,
    })
    candidates.value = page.candidates
    targetStore.replace(scope, page.candidates)
    await loadList()
  } catch (error) {
    problem.value = error instanceof AdminApiError ? error : null
  } finally {
    loading.value = false
  }
}

const selectAllAuthorized = () => {
  selected.value = candidates.value.map(candidate => ({
    target_resource_key: candidate.target_resource_key,
    target_role: candidate.target_role,
    target_id: candidate.target_id,
  }))
}

const boundaryLabel = (row: UnknownRecord): string => {
  const boundary = isRecord(row.boundary_target) ? row.boundary_target : null
  return boundary === null ? '-' : stringValue(boundary.label, stringValue(boundary.target_id, '-'))
}

const openCreate = async () => {
  const target = selected.value[0]
  if (target === undefined || selected.value.length !== 1) return
  const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/example/reference-items/candidates', {
    params: { query: { target_resource_key: target.target_resource_key, target_role: target.target_role, target_id: target.target_id } },
  }))
  references.value = apiCollection(response).items
  createForm.title = ''
  createForm.referenceItemId = ''
  createProblem.value = null
  createOpen.value = true
}

const createWorkItem = async () => {
  const target = selected.value[0]
  if (target === undefined || selected.value.length !== 1) return
  createLoading.value = true
  try {
    await runtime.tenantClient.POST('/api/v1/example/work-items', {
      params: { header: { 'Idempotency-Key': globalThis.crypto.randomUUID() } },
      body: {
        target,
        reference_item_id: createForm.referenceItemId,
        title: createForm.title,
      },
    }).then(runtime.unwrap)
    createOpen.value = false
    await loadList()
  } catch (error) {
    createProblem.value = error instanceof AdminApiError ? error : null
  } finally {
    createLoading.value = false
  }
}

watch(selected, loadList, { deep: true })
watch(viewMode, mode => { void (mode === 'aggregate' ? loadAggregate() : loadList()) })
onMounted(initialize)
</script>

<template>
  <PageContent>
    <PageHeader>
      示例工作项
      <template #actions>
        <el-button
          type="primary"
          :disabled="!canCreate || selected.length !== 1 || viewMode !== 'list'"
          @click="openCreate"
        >
          新建工作项
        </el-button>
      </template>
    </PageHeader>
    <PageToolbar label="工作项范围">
      <el-radio-group
        v-model="viewMode"
        size="small"
      >
        <el-radio-button value="list">
          明细
        </el-radio-button>
        <el-radio-button value="aggregate">
          只读汇总
        </el-radio-button>
      </el-radio-group>
      <TargetSelector
        v-if="viewMode === 'list' && scopeMode === 'multiple'"
        v-model="selected"
        :candidates="candidates"
        multiple
        :loading="loading"
      />
      <el-button
        v-if="viewMode === 'list' && scopeMode === 'multiple'"
        @click="selectAllAuthorized"
      >
        选择全部已授权
      </el-button>
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
    <template v-else-if="viewMode === 'aggregate'">
      <TargetScopeSummary
        mode="aggregate"
        :available-count="candidates.length"
        :selected-count="selected.length"
      />
      <el-descriptions
        v-if="aggregate"
        :column="3"
        border
        class="detail-list"
      >
        <el-descriptions-item
          v-for="(value, key) in aggregate"
          :key="key"
          :label="String(key)"
        >
          {{ value }}
        </el-descriptions-item>
      </el-descriptions>
      <EmptyState
        v-else-if="!loading"
        title="暂无汇总"
        message="当前授权范围没有可汇总的数据。"
      />
    </template>
    <template v-else>
      <TargetScopeSummary
        :mode="scopeMode"
        :available-count="candidates.length"
        :selected-count="selected.length"
      />
      <EmptyState
        v-if="scopeMode === 'zero'"
        title="没有可用目标"
        message="当前操作没有可写入或读取的目标。"
      />
      <EmptyState
        v-else-if="selected.length === 0"
        title="请选择目标"
        message="选择一个或多个已授权目标后加载工作项。"
      />
      <el-table
        v-else
        v-loading="loading"
        :data="rows"
        class="resource-table"
      >
        <el-table-column
          prop="title"
          label="工作项"
          min-width="240"
        />
        <el-table-column
          v-if="scopeMode === 'multiple'"
          label="归属目标"
          min-width="180"
        >
          <template #default="tableScope">
            {{ boundaryLabel(tableScope.row) }}
          </template>
        </el-table-column>
        <el-table-column
          prop="status"
          label="状态"
          width="120"
        />
        <el-table-column
          prop="revision"
          label="版本"
          width="100"
        />
      </el-table>
    </template>

    <el-dialog
      v-model="createOpen"
      title="新建工作项"
      width="min(92vw, 520px)"
      destroy-on-close
    >
      <el-form
        label-position="top"
        @submit.prevent="createWorkItem"
      >
        <el-alert
          v-if="createProblem"
          :title="createProblem.problem.detail"
          type="warning"
          :closable="false"
          class="dialog-alert"
        >
          <template #default>
            请求编号：{{ createProblem.problem.request_id }}
            <el-button
              v-if="createProblem.problem.status === 412"
              text
              @click="createOpen = false; initialize()"
            >
              重新加载
            </el-button>
          </template>
        </el-alert>
        <el-form-item
          label="标题"
          required
        >
          <el-input
            v-model="createForm.title"
            maxlength="200"
          />
        </el-form-item>
        <el-form-item
          label="统一主档"
          required
        >
          <el-select
            v-model="createForm.referenceItemId"
            filterable
            class="full-width"
          >
            <el-option
              v-for="reference in references"
              :key="String(reference.id)"
              :value="String(reference.id)"
              :label="String(reference.name)"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createOpen = false">
          取消
        </el-button>
        <el-button
          type="primary"
          :loading="createLoading"
          :disabled="!createForm.title || !createForm.referenceItemId"
          @click="createWorkItem"
        >
          创建
        </el-button>
      </template>
    </el-dialog>
  </PageContent>
</template>
