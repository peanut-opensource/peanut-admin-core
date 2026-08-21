<script setup lang="ts">
import {
  createDataPolicyDraft,
  createGovernanceCatalog,
  createRolePermissionDraft,
  explainMenuVisibility,
  hasPermission,
  normalizeAuditFilter,
  projectAuditDetail,
  usePlatformContext,
  useTenantContext,
} from '@peanut-admin/admin/core'
import { PageContent, PageHeader, PageToolbar } from '@peanut-admin/admin/shell'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { apiCollection, envelopeData, isRecord, stringArray, stringValue } from '../../app/contracts'
import type { UnknownRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { APP_NAVIGATION, TRUSTED_MENU_ROUTE_CONTRACTS } from '../../app/routes'
import { createGovernanceIconPresentation } from '../../features/governance/icon'
import { createGovernanceWorkbenchModel } from '../../features/governance/model'
import { resolveGovernanceRoleSelection } from '../../features/governance/role-selection'

const runtime = useAdminRuntime()
const route = useRoute()
const tenantContext = useTenantContext()
const platformContext = usePlatformContext()
const audience = computed<'tenant' | 'platform'>(() => route.meta.audience === 'platform' ? 'platform' : 'tenant')
const model = computed(() => createGovernanceWorkbenchModel(audience.value))
const icon = createGovernanceIconPresentation('⚙', '权限治理')
const roles = ref<UnknownRecord[]>([])
const permissions = ref<UnknownRecord[]>([])
const auditEvents = ref<UnknownRecord[]>([])
const menus = ref<UnknownRecord[]>([])
const selectedRoleId = ref('')
const selectedRoleDetail = ref<UnknownRecord | null>(null)
const selectedPermissionKeys = ref<string[]>([])
const roleSelectionReady = ref(false)
const policyResource = ref('')
const policyOperation = ref('')
const policyJson = ref(JSON.stringify({ status: 'disabled', reason: null, valid_from: null, valid_until: null, groups: [] }, null, 2))
const policyRevision = ref<number | null>(null)
const filterEventType = ref('')
const filterAction = ref('')
const filterOutcome = ref<'' | 'success' | 'denied' | 'error'>('')
const filterRequestId = ref('')
const selectedAudit = ref<ReturnType<typeof projectAuditDetail> | null>(null)
const loading = ref(false)
const saving = ref(false)
const failure = ref<{ detail: string, requestId: string } | null>(null)
const success = ref('')

const permissionSet = computed(() => audience.value === 'tenant'
  ? tenantContext.permissionSet
  : platformContext.permissionSet)
const has = (tenant: string, platform: string) => hasPermission(
  permissionSet.value,
  audience.value === 'tenant' ? tenant : platform,
)
const canPermissions = computed(() => has('core.permission.read', 'platform.permission.read'))
const canAssign = computed(() => has('core.role.permission.assign', 'platform.role.permission.assign'))
const canReadPolicy = computed(() => audience.value === 'tenant' && hasPermission(permissionSet.value, 'core.role.data-policy.read'))
const canManagePolicy = computed(() => audience.value === 'tenant' && hasPermission(permissionSet.value, 'core.role.data-policy.manage'))
const canAudit = computed(() => has('core.audit.read', 'platform.audit.read'))
const selectedRole = computed(() => selectedRoleDetail.value)

const errorView = (error: unknown) => error instanceof AdminApiError
  ? { detail: error.problem.detail, requestId: error.problem.request_id }
  : { detail: '治理数据处理失败，请刷新后重试。', requestId: '' }

const auditQuery = () => {
  const filter = normalizeAuditFilter({
    ...(filterEventType.value === '' ? {} : { eventType: filterEventType.value }),
    ...(filterAction.value === '' ? {} : { action: filterAction.value }),
    ...(filterOutcome.value === '' ? {} : { outcome: filterOutcome.value }),
    ...(filterRequestId.value === '' ? {} : { requestId: filterRequestId.value }),
  })
  return {
    page: 1,
    page_size: 100,
    ...(filter.eventType === undefined ? {} : { event_type: filter.eventType }),
    ...(filter.action === undefined ? {} : { action: filter.action }),
    ...(filter.outcome === undefined ? {} : { outcome: filter.outcome }),
    ...(filter.requestId === undefined ? {} : { request_id: filter.requestId }),
  }
}

const loadAudit = async () => {
  if (!canAudit.value) {
    auditEvents.value = []
    return
  }
  const response = audience.value === 'tenant'
    ? await runtime.tenantClient.GET('/api/v1/audit-events', { params: { query: auditQuery() } })
    : await runtime.platformClient.GET('/api/platform/v1/audit-events', { params: { query: auditQuery() } })
  auditEvents.value = apiCollection(runtime.unwrap(response)).items
  selectedAudit.value = null
}

const selectRole = async (roleId: string) => {
  selectedRoleId.value = roleId
  selectedRoleDetail.value = null
  roleSelectionReady.value = false
  selectedPermissionKeys.value = []
  const role = roles.value.find(item => item.id === roleId)
  policyRevision.value = null
  if (role === undefined) return
  try {
    const selection = await resolveGovernanceRoleSelection(
      audience.value,
      roleId,
      role,
      async selectedId => envelopeData(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/roles/{role_id}', {
        params: { path: { role_id: selectedId } },
      }))),
    )
    if (selectedRoleId.value !== roleId) return
    selectedRoleDetail.value = selection.role
    selectedPermissionKeys.value = selection.permissionKeys
    model.value.setRoleDraft({
      roleId: selection.roleId,
      revision: selection.revision,
      permissionKeys: selection.permissionKeys,
    })
    roleSelectionReady.value = true
  } catch (error) {
    if (selectedRoleId.value === roleId) failure.value = errorView(error)
  }
}

const load = async () => {
  loading.value = true
  failure.value = null
  success.value = ''
  try {
    const [rolesResponse, permissionResponse, menuResponse] = audience.value === 'tenant'
      ? await Promise.all([
          runtime.tenantClient.GET('/api/v1/roles', { params: { query: { page: 1, page_size: 100 } } }),
          canPermissions.value ? runtime.tenantClient.GET('/api/v1/permissions') : Promise.resolve(null),
          runtime.tenantClient.GET('/api/v1/menu-diagnostics'),
        ])
      : await Promise.all([
          runtime.platformClient.GET('/api/platform/v1/roles', { params: { query: { page: 1, page_size: 100 } } }),
          canPermissions.value ? runtime.platformClient.GET('/api/platform/v1/permissions') : Promise.resolve(null),
          runtime.platformClient.GET('/api/platform/v1/menu-diagnostics'),
        ])
    roles.value = apiCollection(runtime.unwrap(rolesResponse)).items
    permissions.value = permissionResponse === null ? [] : apiCollection(runtime.unwrap(permissionResponse)).items
    menus.value = apiCollection(runtime.unwrap(menuResponse)).items
    if (selectedRoleId.value !== '' && roles.value.some(role => role.id === selectedRoleId.value)) {
      await selectRole(selectedRoleId.value)
    } else if (roles.value[0]?.id !== undefined) {
      await selectRole(String(roles.value[0].id))
    }
    await loadAudit()
  } catch (error) {
    failure.value = errorView(error)
  } finally {
    loading.value = false
  }
}

const governanceCatalog = () => createGovernanceCatalog({
  permissions: permissions.value.map(permission => ({
    key: stringValue(permission.key),
    moduleKey: stringValue(permission.module_key),
    audience: stringValue(permission.key).startsWith('platform.') ? 'platform' : 'tenant',
    active: true,
  })),
  routes: APP_NAVIGATION.routes().flatMap(localRoute => {
    const contract = TRUSTED_MENU_ROUTE_CONTRACTS[localRoute.name]
    const permissionKeys = localRoute.permissionKeys ?? (localRoute.permission === undefined ? [] : [localRoute.permission])
    if (contract === undefined || permissionKeys.length === 0 || localRoute.audience !== audience.value) return []
    return [{
      ...localRoute,
      moduleKey: localRoute.moduleKey ?? (localRoute.audience === 'platform' ? 'platform' : 'core'),
      permissionKeys,
      componentKey: contract.componentKey,
      clientKeys: contract.clientKeys,
    }]
  }),
  icons: {},
})

const savePermissions = async () => {
  const role = selectedRole.value
  if (role === null || !roleSelectionReady.value) {
    failure.value = { detail: '角色权限快照尚未完整加载，未发送保存请求。', requestId: '' }
    return
  }
  saving.value = true
  failure.value = null
  success.value = ''
  try {
    const revision = Number.parseInt(stringValue(role.revision, '0'), 10)
    const draft = createRolePermissionDraft({
      audience: audience.value,
      roleId: selectedRoleId.value,
      currentRevision: revision,
      ifMatch: `"rev-${revision}"`,
      permissionKeys: selectedPermissionKeys.value,
      availableModules: audience.value === 'tenant' ? tenantContext.moduleSet : new Set(['platform']),
      catalog: governanceCatalog(),
    })
    if (audience.value === 'tenant') {
      runtime.unwrap(await runtime.tenantClient.PUT('/api/v1/roles/{role_id}/permissions', {
        params: { path: { role_id: draft.roleId }, header: { 'If-Match': `"rev-${draft.expectedRevision}"` } },
        body: draft.payload,
      }))
    } else {
      runtime.unwrap(await runtime.platformClient.PUT('/api/platform/v1/roles/{role_id}/permissions', {
        params: { path: { role_id: draft.roleId }, header: { 'If-Match': `"rev-${draft.expectedRevision}"` } },
        body: { ...draft.payload, change_reason: 'governance_workbench' },
      }))
    }
    success.value = '角色权限已保存。'
    await load()
  } catch (error) {
    failure.value = errorView(error)
  } finally {
    saving.value = false
  }
}

const loadPolicy = async () => {
  if (selectedRoleId.value === '' || policyResource.value === '' || policyOperation.value === '') return
  loading.value = true
  failure.value = null
  try {
    const response = runtime.unwrap(await runtime.tenantClient.GET('/api/v1/roles/{role_id}/data-policies/{resource_key}/{operation}', {
      params: { path: { role_id: selectedRoleId.value, resource_key: policyResource.value, operation: policyOperation.value } },
    }))
    const value = isRecord(response) && isRecord(response.data) ? response.data : null
    if (value === null) throw new Error('DATA_POLICY_RESPONSE_INVALID')
    policyRevision.value = Number.parseInt(stringValue(value.revision, '0'), 10)
    policyJson.value = JSON.stringify({
      status: value.status,
      reason: value.reason,
      valid_from: value.valid_from,
      valid_until: value.valid_until,
      groups: value.groups,
    }, null, 2)
  } catch (error) {
    failure.value = errorView(error)
  } finally {
    loading.value = false
  }
}

const savePolicy = async () => {
  if (audience.value !== 'tenant' || selectedRoleId.value === '') return
  saving.value = true
  failure.value = null
  success.value = ''
  try {
    const payload = JSON.parse(policyJson.value) as never
    const draft = policyRevision.value === null
      ? createDataPolicyDraft({ mode: 'create', audience: 'tenant', roleId: selectedRoleId.value, resourceKey: policyResource.value, operation: policyOperation.value, payload })
      : createDataPolicyDraft({ mode: 'update', audience: 'tenant', roleId: selectedRoleId.value, resourceKey: policyResource.value, operation: policyOperation.value, payload, currentRevision: policyRevision.value, ifMatch: `"rev-${policyRevision.value}"` })
    runtime.unwrap(await runtime.tenantClient.PUT('/api/v1/roles/{role_id}/data-policies/{resource_key}/{operation}', {
      params: {
        path: { role_id: draft.roleId, resource_key: draft.resourceKey, operation: draft.operation },
        header: draft.expectedRevision === null ? {} : { 'If-Match': `"rev-${draft.expectedRevision}"` },
      },
      body: draft.payload,
    }))
    success.value = '数据策略已保存。'
    await loadPolicy()
  } catch (error) {
    failure.value = errorView(error)
  } finally {
    saving.value = false
  }
}

const flattenedMenus = computed(() => {
  const rows: UnknownRecord[] = []
  const walk = (items: UnknownRecord[]) => {
    for (const item of items) {
      rows.push(item)
      if (Array.isArray(item.children)) walk(item.children.filter(isRecord))
    }
  }
  walk(menus.value)
  return rows
})

const menuContract = (menu: UnknownRecord): string => {
  if (menu.type !== 'page') return '结构节点'
  try {
    const explanation = explainMenuVisibility({
      key: stringValue(menu.key),
      type: 'page',
      audience: audience.value,
      routeName: stringValue(menu.route_name) || null,
      routePath: stringValue(menu.route_path) || null,
      componentKey: stringValue(menu.component_key) || null,
      requiredPermission: stringValue(menu.required_permission) || null,
      moduleKey: stringValue(menu.module_key),
      clientKeys: stringArray(menu.client_keys),
      icon: null,
    }, {
      audience: audience.value,
      clientKey: runtime.config[audience.value].clientKey,
      deploymentModules: audience.value === 'tenant' ? tenantContext.moduleSet : new Set(['platform']),
      tenantModules: audience.value === 'tenant' ? tenantContext.moduleSet : new Set<string>(),
      permissions: permissionSet.value,
    }, governanceCatalog())
    const serverVisible = menu.visible === true
    const serverReason = stringValue(menu.reason)
    const serverPath = stringValue(menu.trusted_route_path) || null
    if (serverVisible !== explanation.visible || serverReason !== explanation.reason || serverPath !== explanation.trustedPath) {
      return '契约不匹配'
    }
    return explanation.visible ? `匹配：${explanation.trustedPath ?? '-'}` : `隐藏：${explanation.reason}`
  } catch {
    return '契约不匹配'
  }
}

const inspectAudit = async (event: UnknownRecord) => {
  failure.value = null
  try {
    const id = stringValue(event.id)
    const response = audience.value === 'tenant'
      ? await runtime.tenantClient.GET('/api/v1/audit-events/{event_id}', { params: { path: { event_id: id } } })
      : await runtime.platformClient.GET('/api/platform/v1/audit-events/{event_id}', { params: { path: { event_id: id } } })
    const value = envelopeData(runtime.unwrap(response))
    if (!isRecord(value)) throw new Error('AUDIT_DETAIL_INVALID')
    const outcome = stringValue(value.outcome)
    selectedAudit.value = projectAuditDetail({
      id,
      audience: audience.value,
      eventType: stringValue(value.event_type),
      action: stringValue(value.action),
      outcome: ['success', 'denied', 'error'].includes(outcome) ? outcome as 'success' | 'denied' | 'error' : 'error',
      requestId: stringValue(value.request_id),
      occurredAt: stringValue(value.created_at),
      metadata: isRecord(value.metadata) ? value.metadata : {},
    }, ['module_key', 'operation', 'permission_count', 'reason', 'resource_key', 'revision', 'role_id', 'status'])
    model.value.setAuditDetail(selectedAudit.value)
  } catch (error) {
    failure.value = errorView(error)
  }
}

onMounted(load)
</script>

<template>
  <PageContent>
    <PageHeader>
      <span
        :role="icon.role"
        :aria-label="icon.label"
      >{{ icon.glyph }}</span> {{ audience === 'tenant' ? '权限治理' : '平台权限治理' }}
    </PageHeader>
    <PageToolbar label="治理工作台操作">
      <el-button
        :loading="loading"
        @click="load"
      >
        刷新
      </el-button>
    </PageToolbar>
    <el-alert
      v-if="failure"
      type="error"
      :closable="false"
      :title="failure.detail"
    >
      请求编号：{{ failure.requestId || '-' }}
    </el-alert>
    <el-alert
      v-if="success"
      type="success"
      :closable="false"
      :title="success"
    />

    <el-tabs>
      <el-tab-pane label="角色权限">
        <el-select
          v-model="selectedRoleId"
          placeholder="选择角色"
          @change="selectRole"
        >
          <el-option
            v-for="role in roles"
            :key="String(role.id)"
            :label="String(role.name)"
            :value="String(role.id)"
          />
        </el-select>
        <el-checkbox-group
          v-if="canPermissions"
          v-model="selectedPermissionKeys"
          class="permission-grid"
        >
          <el-checkbox
            v-for="permission in permissions"
            :key="String(permission.key)"
            :value="String(permission.key)"
          >
            {{ permission.name }}（{{ permission.key }}）
          </el-checkbox>
        </el-checkbox-group>
        <el-alert
          v-else
          type="info"
          :closable="false"
          :title="audience === 'tenant' ? '缺少 core.permission.read，权限目录不可见。' : '缺少 platform.permission.read，权限目录不可见。'"
        />
        <el-button
          type="primary"
          :disabled="!canAssign || !canPermissions || !roleSelectionReady || selectedRoleId === ''"
          :loading="saving"
          @click="savePermissions"
        >
          保存角色权限
        </el-button>
      </el-tab-pane>

      <el-tab-pane
        v-if="audience === 'tenant'"
        label="数据策略"
      >
        <el-form label-position="top">
          <el-form-item label="资源标识">
            <el-input v-model="policyResource" />
          </el-form-item>
          <el-form-item label="操作">
            <el-input v-model="policyOperation" />
          </el-form-item>
          <el-form-item label="策略 JSON">
            <el-input
              v-model="policyJson"
              type="textarea"
              :rows="12"
            />
          </el-form-item>
        </el-form>
        <el-button
          :disabled="!canReadPolicy || selectedRoleId === ''"
          @click="loadPolicy"
        >
          读取策略
        </el-button>
        <el-button
          type="primary"
          :disabled="!canManagePolicy || selectedRoleId === ''"
          :loading="saving"
          @click="savePolicy"
        >
          保存策略
        </el-button>
      </el-tab-pane>

      <el-tab-pane label="菜单诊断">
        <el-table
          :data="flattenedMenus"
          table-layout="fixed"
        >
          <el-table-column
            prop="name"
            label="菜单"
          />
          <el-table-column
            prop="route_name"
            label="受信路由"
          />
          <el-table-column
            prop="component_key"
            label="组件"
          />
          <el-table-column label="契约">
            <template #default="scope">
              {{ menuContract(scope.row) }}
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="审计日志">
        <el-form inline>
          <el-form-item label="事件">
            <el-input v-model="filterEventType" />
          </el-form-item>
          <el-form-item label="动作">
            <el-input v-model="filterAction" />
          </el-form-item>
          <el-form-item label="结果">
            <el-select
              v-model="filterOutcome"
              clearable
            >
              <el-option
                label="成功"
                value="success"
              /><el-option
                label="拒绝"
                value="denied"
              /><el-option
                label="错误"
                value="error"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="请求编号">
            <el-input v-model="filterRequestId" />
          </el-form-item>
          <el-form-item>
            <el-button
              :disabled="!canAudit"
              @click="loadAudit"
            >
              筛选
            </el-button>
          </el-form-item>
        </el-form>
        <el-table
          :data="auditEvents"
          table-layout="fixed"
          @row-click="inspectAudit"
        >
          <el-table-column
            prop="created_at"
            label="时间"
          />
          <el-table-column
            prop="event_type"
            label="事件"
          />
          <el-table-column
            prop="action"
            label="动作"
          />
          <el-table-column
            prop="request_id"
            label="请求编号"
          />
        </el-table>
        <el-descriptions
          v-if="selectedAudit"
          :column="1"
          border
          class="audit-detail"
        >
          <el-descriptions-item label="事件">
            {{ selectedAudit.eventType }}
          </el-descriptions-item>
          <el-descriptions-item label="动作">
            {{ selectedAudit.action }}
          </el-descriptions-item>
          <el-descriptions-item label="结果">
            {{ selectedAudit.outcome }}
          </el-descriptions-item>
          <el-descriptions-item label="请求编号">
            {{ selectedAudit.requestId }}
          </el-descriptions-item>
          <el-descriptions-item label="安全元数据">
            <pre>{{ JSON.stringify(selectedAudit.metadata, null, 2) }}</pre>
          </el-descriptions-item>
        </el-descriptions>
        <el-alert
          v-if="!canAudit"
          type="info"
          :closable="false"
          :title="audience === 'tenant' ? '缺少 core.audit.read，审计数据不可见。' : '缺少 platform.audit.read，审计数据不可见。'"
        />
      </el-tab-pane>
    </el-tabs>
  </PageContent>
</template>

<style scoped>
.permission-grid { display: grid; gap: 8px; margin: 16px 0; }
.el-input { margin-bottom: 12px; }
.audit-detail { margin-top: 16px; }
pre { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
