<script setup lang="ts">
import {
  ConflictState,
  ForbiddenState,
  ModuleUnavailableState,
  NotFoundState,
  RateLimitState,
  ServiceUnavailableState,
  SessionExpiredState,
} from '@peanut-admin/admin/shell'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useWorkspaceStore } from '../../app/store'

const route = useRoute()
const router = useRouter()
const workspace = useWorkspaceStore()
const state = computed(() => String(route.name))
const requestId = computed(() => workspace.problem?.request_id ?? 'unavailable')
const problemStatus = computed(() => workspace.problem?.status ?? null)
const problemCode = computed(() => workspace.problem?.code ?? String(route.query.code ?? ''))
const retryAfter = computed(() => typeof route.query.retry_after === 'string' ? route.query.retry_after : null)
const message = computed(() => {
  if (workspace.problem !== null) return workspace.problem.detail
  if (String(route.query.code).startsWith('MODULE_')) return 'This module is currently unavailable.'
  return '当前请求无法完成。'
})
const statusKind = computed(() => {
  if (state.value === 'state.forbidden' || problemStatus.value === 403) return 'forbidden'
  if (state.value === 'state.not-found' || problemStatus.value === 404) return 'not-found'
  if (problemStatus.value === 409 || problemStatus.value === 412) return 'conflict'
  if (problemStatus.value === 429 || problemCode.value === 'RATE_LIMITED') return 'rate-limit'
  if (problemStatus.value === 401 || problemCode.value.includes('SESSION_EXPIRED')) return 'session-expired'
  if (problemCode.value.startsWith('MODULE_')) return 'module-unavailable'
  return 'service-unavailable'
})

const retry = () => {
  workspace.problem = null
  if (window.history.length > 1) router.back()
  else void router.replace('/')
}

const signIn = () => {
  workspace.problem = null
  void router.replace(workspace.activeAudience === 'platform' ? '/platform/login' : '/login')
}
</script>

<template>
  <main class="standalone-state">
    <ForbiddenState
      v-if="statusKind === 'forbidden'"
      :request-id="requestId"
    />
    <NotFoundState
      v-else-if="statusKind === 'not-found'"
      :message="message"
      :request-id="requestId"
    />
    <ConflictState
      v-else-if="statusKind === 'conflict'"
      :message="message"
      :request-id="requestId"
      @action="retry"
    />
    <RateLimitState
      v-else-if="statusKind === 'rate-limit'"
      :message="message"
      :request-id="requestId"
      :retry-after="retryAfter"
    />
    <SessionExpiredState
      v-else-if="statusKind === 'session-expired'"
      :message="message"
      :request-id="requestId"
      @action="signIn"
    />
    <ModuleUnavailableState
      v-else-if="statusKind === 'module-unavailable'"
      :message="message"
      :request-id="requestId"
      @action="retry"
    />
    <ServiceUnavailableState
      v-else
      :message="message"
      :request-id="requestId"
      @action="retry"
    />
    <el-button
      v-if="statusKind === 'forbidden' || statusKind === 'not-found'"
      @click="retry"
    >
      返回
    </el-button>
  </main>
</template>
