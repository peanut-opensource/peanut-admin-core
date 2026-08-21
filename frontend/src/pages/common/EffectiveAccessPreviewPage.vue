<script setup lang="ts">
import { EmptyState, ForbiddenState, ModuleUnavailableState, PageContent, PageHeader, PageToolbar } from '@peanut-admin/admin/shell'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { envelopeData, isRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'

interface EffectiveAccessMember {
  id: string
  displayName: string | null
  status: string
  primaryDepartmentId: string | null
  effective: boolean
}

interface EffectiveAccessRole {
  id: string
  key: string
  name: string
  isBuiltin: boolean
}

interface EffectiveAccessCondition {
  conditionKey: string
  targetResourceKey: string | null
  targetCount: number
}

interface EffectiveAccessGroup {
  sourceRoleKey: string
  conditionMatch: 'all'
  conditions: EffectiveAccessCondition[]
}

interface EffectiveDataAccess {
  mode: string
  runtimeDecisionRequired: boolean
  groupMatch: 'any'
  groups: EffectiveAccessGroup[]
}

interface EffectiveResourceOperation {
  resourceKey: string
  moduleKey: string
  operation: string
  ownership: string
  accessMode: string
  targetCardinality: string
  permissionMatch: string
  requiredPermissionKeys: string[]
  functionalAllowed: boolean
  dataAccess: EffectiveDataAccess
}

interface EffectiveAccessPreview {
  previewKind: 'authorization_inputs'
  evaluatedAt: string
  snapshotRevision: string
  member: EffectiveAccessMember
  roles: EffectiveAccessRole[]
  permissionKeys: string[]
  resourceOperations: EffectiveResourceOperation[]
}

interface PageMeta {
  requestId: string
  page: number
  pageSize: number
  total: number
  totalPages: number
}

interface Failure {
  status: number
  detail: string
  requestId: string
}

const route = useRoute()
const router = useRouter()
const runtime = useAdminRuntime()
const preview = ref<EffectiveAccessPreview | null>(null)
const meta = ref<PageMeta>({ requestId: '', page: 1, pageSize: 20, total: 0, totalPages: 0 })
const loading = ref(false)
const failure = ref<Failure | null>(null)
const requestedPage = ref(1)
const requestedPageSize = ref(20)
const compactViewport = ref(false)
let loadGeneration = 0

const memberId = computed(() => typeof route.params.member_id === 'string' ? route.params.member_id : '')

const requiredString = (value: unknown): string => {
  if (typeof value !== 'string' || value === '') throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  return value
}

const nullableString = (value: unknown): string | null => {
  if (value === null) return null
  return requiredString(value)
}

const requiredBoolean = (value: unknown): boolean => {
  if (typeof value !== 'boolean') throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  return value
}

const requiredInteger = (value: unknown): number => {
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 0) {
    throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  }
  return value
}

const strings = (value: unknown): string[] => {
  if (!Array.isArray(value) || !value.every(item => typeof item === 'string' && item !== '')) {
    throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  }
  return [...value]
}

const parseCondition = (value: unknown): EffectiveAccessCondition => {
  if (!isRecord(value)) throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  return {
    conditionKey: requiredString(value.condition_key),
    targetResourceKey: nullableString(value.target_resource_key),
    targetCount: requiredInteger(value.target_count),
  }
}

const parseGroup = (value: unknown): EffectiveAccessGroup => {
  if (!isRecord(value) || value.condition_match !== 'all' || !Array.isArray(value.conditions)) {
    throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  }
  return {
    sourceRoleKey: requiredString(value.source_role_key),
    conditionMatch: 'all',
    conditions: value.conditions.map(parseCondition),
  }
}

const parseDataAccess = (value: unknown): EffectiveDataAccess => {
  if (!isRecord(value) || value.group_match !== 'any' || !Array.isArray(value.groups)) {
    throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  }
  return {
    mode: requiredString(value.mode),
    runtimeDecisionRequired: requiredBoolean(value.runtime_decision_required),
    groupMatch: 'any',
    groups: value.groups.map(parseGroup),
  }
}

const parseOperation = (value: unknown): EffectiveResourceOperation => {
  if (!isRecord(value)) throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  return {
    resourceKey: requiredString(value.resource_key),
    moduleKey: requiredString(value.module_key),
    operation: requiredString(value.operation),
    ownership: requiredString(value.ownership),
    accessMode: requiredString(value.access_mode),
    targetCardinality: requiredString(value.target_cardinality),
    permissionMatch: requiredString(value.permission_match),
    requiredPermissionKeys: strings(value.required_permission_keys),
    functionalAllowed: requiredBoolean(value.functional_allowed),
    dataAccess: parseDataAccess(value.data_access),
  }
}

const parsePreview = (value: unknown): EffectiveAccessPreview => {
  const data = envelopeData(value)
  if (!isRecord(data)
    || data.preview_kind !== 'authorization_inputs'
    || !isRecord(data.member)
    || !Array.isArray(data.roles)
    || !Array.isArray(data.resource_operations)) {
    throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  }

  const roles = data.roles.map((role): EffectiveAccessRole => {
    if (!isRecord(role)) throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
    return {
      id: requiredString(role.id),
      key: requiredString(role.key),
      name: requiredString(role.name),
      isBuiltin: requiredBoolean(role.is_builtin),
    }
  })

  return {
    previewKind: 'authorization_inputs',
    evaluatedAt: requiredString(data.evaluated_at),
    snapshotRevision: requiredString(data.snapshot_revision),
    member: {
      id: requiredString(data.member.id),
      displayName: nullableString(data.member.display_name),
      status: requiredString(data.member.status),
      primaryDepartmentId: nullableString(data.member.primary_department_id),
      effective: requiredBoolean(data.member.effective),
    },
    roles,
    permissionKeys: strings(data.permission_keys),
    resourceOperations: data.resource_operations.map(parseOperation),
  }
}

const parseMeta = (value: unknown): PageMeta => {
  if (!isRecord(value) || !isRecord(value.meta)) throw new Error('EFFECTIVE_ACCESS_RESPONSE_INVALID')
  return {
    requestId: requiredString(value.meta.request_id),
    page: requiredInteger(value.meta.page),
    pageSize: requiredInteger(value.meta.page_size),
    total: requiredInteger(value.meta.total),
    totalPages: requiredInteger(value.meta.total_pages),
  }
}

const failureFrom = (error: unknown): Failure => error instanceof AdminApiError
  ? {
      status: error.problem.status,
      detail: error.problem.detail,
      requestId: error.problem.request_id,
    }
  : {
      status: 500,
      detail: '有效访问暂时无法加载，请稍后重试。',
      requestId: '',
    }

const load = async () => {
  const requestedMemberId = memberId.value
  const generation = ++loadGeneration
  if (requestedMemberId === '') {
    preview.value = null
    failure.value = null
    loading.value = false
    return
  }
  loading.value = true
  failure.value = null
  try {
    const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/members/{member_id}/effective-access', {
      params: {
        path: { member_id: requestedMemberId },
        query: { page: requestedPage.value, page_size: requestedPageSize.value },
      },
    }))
    if (generation !== loadGeneration) return
    preview.value = parsePreview(response)
    meta.value = parseMeta(response)
    requestedPage.value = meta.value.page
    requestedPageSize.value = meta.value.pageSize
  } catch (error) {
    if (generation !== loadGeneration) return
    preview.value = null
    failure.value = failureFrom(error)
  } finally {
    if (generation === loadGeneration) loading.value = false
  }
}

const changePage = (page: number) => {
  if (loading.value) return
  requestedPage.value = page
  void load()
}

const changePageSize = (pageSize: number) => {
  if (loading.value) return
  requestedPage.value = 1
  requestedPageSize.value = pageSize
  void load()
}

const dataModeLabel = (mode: string): string => ({
  functional_denied: '功能权限拒绝',
  tenant_wide: '租户范围',
  global_reference_read: '全局引用读取',
  conditional: '条件授权',
  no_effective_policy: '无有效策略',
  tenant_actor_denied: '租户主体拒绝',
})[mode] ?? mode

const statusLabel = (status: string): string => ({
  pending: '待激活',
  active: '有效',
  suspended: '已停用',
  left: '已离开',
})[status] ?? status

const syncCompactViewport = () => {
  compactViewport.value = window.innerWidth <= 760
}

watch(memberId, () => {
  requestedPage.value = 1
  preview.value = null
  failure.value = null
  void load()
}, { immediate: true })

onMounted(() => {
  syncCompactViewport()
  window.addEventListener('resize', syncCompactViewport)
})
onUnmounted(() => window.removeEventListener('resize', syncCompactViewport))
</script>

<template>
  <PageContent>
    <PageHeader>有效访问预览</PageHeader>
    <PageToolbar label="预览操作">
      <el-button @click="router.push('/app/members')">
        返回成员列表
      </el-button>
      <el-button
        :loading="loading"
        @click="load"
      >
        刷新
      </el-button>
    </PageToolbar>

    <ForbiddenState
      v-if="failure?.status === 403"
      title="无权查看"
      :message="failure.detail"
      :request-id="failure.requestId"
    />
    <ModuleUnavailableState
      v-else-if="failure?.status === 404"
      title="成员不可用"
      :message="failure.detail"
      :request-id="failure.requestId"
      action-label="刷新"
      @action="load"
    />
    <ModuleUnavailableState
      v-else-if="failure"
      title="有效访问暂不可用"
      :message="failure.detail"
      :request-id="failure.requestId"
      action-label="刷新"
      @action="load"
    />
    <el-skeleton
      v-else-if="loading"
      :rows="8"
      animated
    />

    <template v-else-if="preview">
      <section class="effective-access-section">
        <h2>成员状态</h2>
        <el-descriptions
          :column="compactViewport ? 1 : 2"
          border
          class="detail-list"
        >
          <el-descriptions-item label="成员">
            {{ preview.member.displayName ?? '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag
              size="small"
              effect="plain"
              :type="preview.member.effective ? 'success' : 'warning'"
            >
              {{ statusLabel(preview.member.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="成员编号">
            <code>{{ preview.member.id }}</code>
          </el-descriptions-item>
          <el-descriptions-item label="主部门">
            <code>{{ preview.member.primaryDepartmentId ?? '-' }}</code>
          </el-descriptions-item>
          <el-descriptions-item label="评估时间">
            {{ preview.evaluatedAt }}
          </el-descriptions-item>
          <el-descriptions-item label="快照版本">
            <code>{{ preview.snapshotRevision }}</code>
          </el-descriptions-item>
        </el-descriptions>
        <p
          v-if="!preview.member.effective"
          class="effective-access-note is-warning"
        >
          当前成员状态不产生有效访问。
        </p>
      </section>

      <section class="effective-access-section">
        <h2>有效角色</h2>
        <div
          v-if="preview.roles.length > 0"
          class="effective-access-list"
        >
          <div
            v-for="role in preview.roles"
            :key="role.id"
            class="effective-access-list__item"
          >
            <strong>{{ role.name }}</strong>
            <code>{{ role.key }}</code>
            <el-tag
              v-if="role.isBuiltin"
              size="small"
              effect="plain"
            >
              内置
            </el-tag>
          </div>
        </div>
        <p
          v-else
          class="effective-access-empty"
        >
          暂无有效角色。
        </p>
      </section>

      <section class="effective-access-section">
        <h2>有效功能权限</h2>
        <ul
          v-if="preview.permissionKeys.length > 0"
          class="effective-access-key-list"
        >
          <li
            v-for="permissionKey in preview.permissionKeys"
            :key="permissionKey"
          >
            <code>{{ permissionKey }}</code>
          </li>
        </ul>
        <p
          v-else
          class="effective-access-empty"
        >
          暂无有效功能权限。
        </p>
      </section>

      <section class="effective-access-section">
        <div class="effective-access-section__header">
          <h2>资源操作范围</h2>
          <span>{{ meta.total }} 项</span>
        </div>
        <div
          v-if="preview.resourceOperations.length > 0"
          class="effective-access-table-wrap"
        >
          <el-table
            :data="preview.resourceOperations"
            class="resource-table effective-access-table"
            table-layout="fixed"
          >
            <el-table-column
              label="资源操作"
              min-width="240"
            >
              <template #default="scope">
                <div class="effective-access-cell">
                  <code>{{ scope.row.resourceKey }}</code>
                  <strong>{{ scope.row.operation }}</strong>
                  <span>模块：<code>{{ scope.row.moduleKey }}</code></span>
                  <span>{{ scope.row.ownership }} / {{ scope.row.accessMode }} / {{ scope.row.targetCardinality }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column
              label="功能权限"
              min-width="220"
            >
              <template #default="scope">
                <div class="effective-access-cell">
                  <el-tag
                    size="small"
                    effect="plain"
                    :type="scope.row.functionalAllowed ? 'success' : 'danger'"
                  >
                    {{ scope.row.functionalAllowed ? '满足' : '不满足' }}
                  </el-tag>
                  <span>匹配：{{ scope.row.permissionMatch }}</span>
                  <code
                    v-for="permissionKey in scope.row.requiredPermissionKeys"
                    :key="permissionKey"
                  >{{ permissionKey }}</code>
                </div>
              </template>
            </el-table-column>
            <el-table-column
              label="数据访问输入"
              min-width="320"
            >
              <template #default="scope">
                <div class="effective-access-cell">
                  <strong>{{ dataModeLabel(scope.row.dataAccess.mode) }}</strong>
                  <span v-if="scope.row.dataAccess.groups.length > 0">策略组匹配：{{ scope.row.dataAccess.groupMatch }}</span>
                  <span
                    v-if="scope.row.dataAccess.runtimeDecisionRequired"
                    class="effective-access-runtime-warning"
                  >仍需运行时判定</span>
                  <div
                    v-for="(group, groupIndex) in scope.row.dataAccess.groups"
                    :key="`${group.sourceRoleKey}-${groupIndex}`"
                    class="effective-access-group"
                  >
                    <span>来源角色：<code>{{ group.sourceRoleKey }}</code></span>
                    <span>组内条件：{{ group.conditionMatch }}</span>
                    <span
                      v-for="(condition, conditionIndex) in group.conditions"
                      :key="`${condition.conditionKey}-${conditionIndex}`"
                    >
                      <code>{{ condition.conditionKey }}</code>
                      <template v-if="condition.targetResourceKey">
                        / <code>{{ condition.targetResourceKey }}</code>
                      </template>
                      / {{ condition.targetCount }} 个目标
                    </span>
                  </div>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>
        <p
          v-else
          class="effective-access-empty"
        >
          暂无可预览的资源操作。
        </p>
        <el-pagination
          v-if="meta.total > 0"
          :current-page="meta.page"
          :page-size="meta.pageSize"
          :total="meta.total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          class="page-pagination"
          :disabled="loading"
          @current-change="changePage"
          @size-change="changePageSize"
        />
      </section>
    </template>

    <EmptyState
      v-else
      title="暂无预览"
      message="当前没有可显示的有效访问快照。"
    />
  </PageContent>
</template>

<style scoped>
.effective-access-section {
  min-width: 0;
  padding-top: 18px;
  border-top: 1px solid var(--pa-shell-border-color);
}

.effective-access-section h2 {
  margin: 0 0 14px;
  font-size: 17px;
  line-height: 1.4;
}

.effective-access-section__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.effective-access-section__header span {
  color: var(--pa-shell-muted-text-color);
  font-size: 13px;
}

.effective-access-list {
  display: grid;
  gap: 8px;
}

.effective-access-list__item {
  display: grid;
  grid-template-columns: minmax(120px, 0.8fr) minmax(180px, 1.4fr) auto;
  align-items: center;
  gap: 12px;
  min-width: 0;
  padding: 10px 12px;
  border-bottom: 1px solid #e8ecea;
}

.effective-access-key-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.effective-access-key-list li {
  min-width: 0;
  padding: 8px 10px;
  border-left: 3px solid #b8d7cb;
  background: #f8faf9;
}

.effective-access-table-wrap {
  width: 100%;
  min-width: 0;
  overflow-x: auto;
}

.effective-access-table {
  min-width: 0;
}

.effective-access-cell {
  display: grid;
  justify-items: start;
  gap: 6px;
  min-width: 0;
}

.effective-access-cell span {
  color: var(--pa-shell-muted-text-color);
  font-size: 12px;
}

.effective-access-group {
  display: grid;
  gap: 4px;
  width: 100%;
  min-width: 0;
  padding-top: 6px;
  border-top: 1px solid #e8ecea;
}

.effective-access-runtime-warning,
.effective-access-note.is-warning {
  color: #774425;
}

.effective-access-note,
.effective-access-empty {
  margin: 10px 0 0;
  color: var(--pa-shell-muted-text-color);
  font-size: 13px;
}

code {
  max-width: 100%;
  overflow-wrap: anywhere;
  color: #315568;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  white-space: normal;
  word-break: break-word;
}

@media (max-width: 760px) {
  .effective-access-list__item,
  .effective-access-key-list {
    grid-template-columns: minmax(0, 1fr);
  }

  .effective-access-section__header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
