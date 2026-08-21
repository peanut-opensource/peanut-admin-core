<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { safeReturnTo } from '../../app/routes'
import AuthFrame from './AuthFrame.vue'

const runtime = useAdminRuntime()
const route = useRoute()
const router = useRouter()
const form = reactive({ email: '', password: '' })
const loading = ref(false)
const errorMessage = ref('')

const submit = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    await runtime.platformLogin(form.email.trim(), form.password)
    await router.replace(safeReturnTo(route.query.return_to, 'platform'))
  } catch (error) {
    errorMessage.value = error instanceof AdminApiError
      ? error.problem.detail
      : '登录响应无法处理，请稍后重试。'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthFrame
    eyebrow="平台控制面"
    title="操作员登录"
  >
    <el-alert
      v-if="errorMessage"
      :title="errorMessage"
      type="error"
      :closable="false"
    />
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
      to="/login"
    >
      返回租户登录
    </RouterLink>
  </AuthFrame>
</template>
