<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { safeReturnTo } from '../../app/routes'
import AuthFrame from './AuthFrame.vue'

const route = useRoute()
const router = useRouter()
const runtime = useAdminRuntime()
const form = reactive({ email: '', password: '', tenantCode: '' })
const loading = ref(false)
const errorMessage = ref('')
const requestId = ref('')

const submit = async () => {
  loading.value = true
  errorMessage.value = ''
  requestId.value = ''
  try {
    const result = await runtime.tenantLogin(
      form.email.trim(),
      form.password,
      form.tenantCode.trim() === '' ? null : form.tenantCode.trim(),
    )
    const returnTo = safeReturnTo(route.query.return_to, 'tenant')
    await router.replace(result.state === 'authenticated'
      ? returnTo
      : { name: 'tenant.select', query: { return_to: returnTo } })
  } catch (error) {
    if (error instanceof AdminApiError) {
      errorMessage.value = error.problem.detail
      requestId.value = error.problem.request_id
    } else {
      errorMessage.value = '登录响应无法处理，请稍后重试。'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthFrame
    eyebrow="租户工作区"
    title="登录"
  >
    <el-alert
      v-if="errorMessage"
      :title="errorMessage"
      type="error"
      :closable="false"
    >
      <template
        v-if="requestId"
        #default
      >
        请求编号：{{ requestId }}
      </template>
    </el-alert>
    <el-form
      class="auth-form"
      label-position="top"
      @submit.prevent="submit"
    >
      <el-form-item
        label="邮箱"
        required
      >
        <el-input
          v-model="form.email"
          type="email"
          autocomplete="username"
          autofocus
        />
      </el-form-item>
      <el-form-item
        label="密码"
        required
      >
        <el-input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          show-password
        />
      </el-form-item>
      <el-form-item label="租户代码">
        <el-input
          v-model="form.tenantCode"
          autocomplete="organization"
        />
      </el-form-item>
      <el-button
        class="auth-submit"
        type="primary"
        native-type="submit"
        :loading="loading"
      >
        登录
      </el-button>
    </el-form>
    <RouterLink
      class="auth-switch"
      to="/platform/login"
    >
      进入平台控制面
    </RouterLink>
  </AuthFrame>
</template>
