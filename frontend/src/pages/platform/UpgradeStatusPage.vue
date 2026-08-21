<script setup lang="ts">
import { PageContent, PageHeader, PageToolbar } from '@peanut-admin/admin/shell'
import { computed, onMounted, ref } from 'vue'

import { envelopeData, isRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'

interface UpgradeStatus {
  state: 'configuration_required' | 'ready' | 'blocked'
  current: { commit: string, tree: string, clean: boolean } | null
  target: { release_id: string, commit: string, tree: string } | null
  preflight: { ready: boolean, code: string }
  backup: { required: boolean, configured: boolean, valid: boolean, source_identity_matches: boolean }
  execution: { mode: 'operator_cli', remote_execution: false, command: string }
}

const runtime = useAdminRuntime()
const status = ref<UpgradeStatus | null>(null)
const loading = ref(false)
const failure = ref<{ detail: string, requestId: string } | null>(null)

const identity = (value: unknown): { commit: string, tree: string, clean: boolean } | null => {
  if (value === null) return null
  if (!isRecord(value) || typeof value.commit !== 'string' || typeof value.tree !== 'string' || typeof value.clean !== 'boolean') {
    throw new Error('UPGRADE_STATUS_RESPONSE_INVALID')
  }
  return { commit: value.commit, tree: value.tree, clean: value.clean }
}

const parseStatus = (value: unknown): UpgradeStatus => {
  const data = envelopeData(value)
  if (!isRecord(data) || !['configuration_required', 'ready', 'blocked'].includes(String(data.state))
    || !isRecord(data.preflight) || !isRecord(data.backup) || !isRecord(data.execution)
    || typeof data.preflight.ready !== 'boolean' || typeof data.preflight.code !== 'string'
    || data.execution.mode !== 'operator_cli' || data.execution.remote_execution !== false
    || typeof data.execution.command !== 'string') throw new Error('UPGRADE_STATUS_RESPONSE_INVALID')
  let target: UpgradeStatus['target'] = null
  if (data.target !== null) {
    if (!isRecord(data.target) || typeof data.target.release_id !== 'string'
      || typeof data.target.commit !== 'string' || typeof data.target.tree !== 'string') {
      throw new Error('UPGRADE_STATUS_RESPONSE_INVALID')
    }
    target = { release_id: data.target.release_id, commit: data.target.commit, tree: data.target.tree }
  }
  return {
    state: data.state as UpgradeStatus['state'],
    current: identity(data.current),
    target,
    preflight: { ready: data.preflight.ready, code: data.preflight.code },
    backup: {
      required: data.backup.required === true,
      configured: data.backup.configured === true,
      valid: data.backup.valid === true,
      source_identity_matches: data.backup.source_identity_matches === true,
    },
    execution: { mode: 'operator_cli', remote_execution: false, command: data.execution.command },
  }
}

const stateLabel = computed(() => ({
  configuration_required: '等待配置升级清单',
  ready: '已满足升级准备条件',
  blocked: '升级准备被阻止',
}[status.value?.state ?? 'configuration_required']))

const short = (value: string | undefined): string => value?.slice(0, 12) ?? '-'

const load = async () => {
  loading.value = true
  failure.value = null
  try {
    status.value = parseStatus(runtime.unwrap(await runtime.platformClient.GET('/api/platform/v1/upgrade')))
  } catch (error) {
    status.value = null
    failure.value = error instanceof AdminApiError
      ? { detail: error.problem.detail, requestId: error.problem.request_id }
      : { detail: '升级状态响应无效，请联系运维人员检查服务配置。', requestId: '' }
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <PageContent>
    <PageHeader>版本与升级</PageHeader>
    <PageToolbar label="升级状态操作">
      <el-button
        :loading="loading"
        @click="load"
      >
        刷新状态
      </el-button>
    </PageToolbar>
    <el-alert
      v-if="failure"
      type="error"
      :closable="false"
      :title="failure.detail"
    >
      <template v-if="failure.requestId">
        请求编号：{{ failure.requestId }}
      </template>
    </el-alert>
    <template v-else-if="status">
      <el-alert
        :type="status.preflight.ready ? 'success' : 'warning'"
        :closable="false"
        :title="stateLabel"
        :description="status.preflight.code"
      />
      <el-descriptions
        :column="1"
        border
        class="upgrade-details"
      >
        <el-descriptions-item label="当前提交">
          {{ short(status.current?.commit) }}
        </el-descriptions-item>
        <el-descriptions-item label="当前 Tree">
          {{ short(status.current?.tree) }}
        </el-descriptions-item>
        <el-descriptions-item label="工作区状态">
          {{ status.current?.clean ? '干净' : '存在未提交变化' }}
        </el-descriptions-item>
        <el-descriptions-item label="目标版本">
          {{ status.target?.release_id ?? '尚未配置' }}
        </el-descriptions-item>
        <el-descriptions-item label="目标提交">
          {{ short(status.target?.commit) }}
        </el-descriptions-item>
        <el-descriptions-item label="备份证据">
          {{ status.backup.valid && status.backup.source_identity_matches ? '有效且匹配当前来源' : '缺失、无效或不匹配' }}
        </el-descriptions-item>
        <el-descriptions-item label="执行方式">
          仅由运维人员在主机终端执行
        </el-descriptions-item>
      </el-descriptions>
      <el-alert
        type="info"
        :closable="false"
        title="后台只展示准备状态，不提供远程执行升级。"
      >
        <code>{{ status.execution.command }}</code>
      </el-alert>
    </template>
  </PageContent>
</template>

<style scoped>
.upgrade-details { margin: 16px 0; }
code { overflow-wrap: anywhere; }
</style>
