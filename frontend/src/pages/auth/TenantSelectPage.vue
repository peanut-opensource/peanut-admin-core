<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { safeReturnTo } from '../../app/routes'
import { useWorkspaceStore } from '../../app/store'
import AuthFrame from './AuthFrame.vue'

const runtime = useAdminRuntime()
const workspace = useWorkspaceStore()
const route = useRoute()
const router = useRouter()
const selection = computed(() => workspace.tenantSelection)
const tenantId = ref(selection.value?.tenants[0]?.tenantId ?? '')
const loading = ref(false)
const errorMessage = ref('')

if (selection.value === null) void router.replace({ name: 'tenant.login' })

const submit = async () => {
  if (selection.value === null || tenantId.value === '') return
  loading.value = true
  errorMessage.value = ''
  try {
    await runtime.selectTenant(selection.value.challengeToken, tenantId.value)
    await router.replace(safeReturnTo(route.query.return_to, 'tenant'))
  } catch (error) {
    errorMessage.value = error instanceof AdminApiError
      ? error.problem.detail
      : '租户选择已失效，请重新登录。'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthFrame
    eyebrow="租户工作区"
    title="选择租户"
  >
    <el-alert
      v-if="errorMessage"
      :title="errorMessage"
      type="error"
      :closable="false"
    />
    <el-radio-group
      v-if="selection"
      v-model="tenantId"
      class="tenant-choice-list"
    >
      <el-radio
        v-for="tenant in selection.tenants"
        :key="tenant.tenantId"
        :value="tenant.tenantId"
        border
      >
        <strong>{{ tenant.tenantName }}</strong>
        <span>{{ tenant.memberDisplayName || tenant.tenantCode }}</span>
      </el-radio>
    </el-radio-group>
    <el-button
      class="auth-submit"
      type="primary"
      :loading="loading"
      :disabled="tenantId === ''"
      @click="submit"
    >
      进入工作区
    </el-button>
    <RouterLink
      class="auth-switch"
      to="/login"
    >
      返回登录
    </RouterLink>
  </AuthFrame>
</template>
