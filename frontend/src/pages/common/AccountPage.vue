<script setup lang="ts">
import { PageContent, PageHeader } from '@peanut-admin/admin/shell'
import { useTenantContext } from '@peanut-admin/admin/core'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { envelopeData, isRecord } from '../../app/contracts'
import { AdminApiError, useAdminRuntime } from '../../app/runtime'
import { useWorkspaceStore } from '../../app/store'

const context = useTenantContext()
const router = useRouter()
const runtime = useAdminRuntime()
const workspace = useWorkspaceStore()

interface AccountCredential {
  kind: string
  identifierType: string
  identifierMasked: string
  verifiedAt: string | null
  secretChangedAt: string | null
}

interface AccountProfile {
  accountId: string
  displayName: string
  avatarUri: string | null
  credential: AccountCredential
}

interface Failure {
  message: string
  requestId: string
}

const accountApi = runtime.tenantClient
const profile = ref<AccountProfile | null>(null)
const profileForm = reactive({ displayName: '', avatarUri: '' })
const passwordForm = reactive({ currentPassword: '', newPassword: '', confirmPassword: '' })
const profileLoading = ref(false)
const profileSaving = ref(false)
const passwordSaving = ref(false)
const loadFailure = ref<Failure | null>(null)
const profileFailure = ref<Failure | null>(null)
const passwordFailure = ref<Failure | null>(null)
const profileSaved = ref(false)
let profileLoadGeneration = 0

const requiredString = (value: unknown): string => {
  if (typeof value !== 'string' || value === '') throw new Error('ACCOUNT_PROFILE_INVALID')
  return value
}

const nullableString = (value: unknown): string | null => {
  if (value === null) return null
  if (typeof value !== 'string') throw new Error('ACCOUNT_PROFILE_INVALID')
  return value
}

const parseProfile = (value: unknown): AccountProfile => {
  const data = envelopeData(value)
  if (!isRecord(data) || !isRecord(data.credential)) throw new Error('ACCOUNT_PROFILE_INVALID')

  return {
    accountId: requiredString(data.account_id),
    displayName: requiredString(data.display_name),
    avatarUri: nullableString(data.avatar_uri),
    credential: {
      kind: requiredString(data.credential.kind),
      identifierType: requiredString(data.credential.identifier_type),
      identifierMasked: requiredString(data.credential.identifier_masked),
      verifiedAt: nullableString(data.credential.verified_at),
      secretChangedAt: nullableString(data.credential.secret_changed_at),
    },
  }
}

const failureFrom = (error: unknown, fallback: string): Failure => error instanceof AdminApiError
  ? { message: error.problem.detail, requestId: error.problem.request_id }
  : { message: fallback, requestId: '' }

const applyProfile = (value: AccountProfile) => {
  profile.value = value
  profileForm.displayName = value.displayName
  profileForm.avatarUri = value.avatarUri ?? ''
}

const clearProfileSaved = () => {
  profileSaved.value = false
}

const loadProfile = async () => {
  const generation = ++profileLoadGeneration
  profileLoading.value = true
  try {
    const loadedProfile = parseProfile(runtime.unwrap(await accountApi.GET('/api/v1/account')))
    if (generation !== profileLoadGeneration) return

    applyProfile(loadedProfile)
    loadFailure.value = null
  } catch (error) {
    if (generation !== profileLoadGeneration) return

    profile.value = null
    loadFailure.value = failureFrom(error, '账号资料暂时无法加载，请稍后重试。')
  } finally {
    if (generation === profileLoadGeneration) profileLoading.value = false
  }
}

const saveProfile = async () => {
  if (profile.value === null) return

  const displayName = profileForm.displayName.trim()
  if (displayName === '') {
    profileFailure.value = { message: '显示名不能为空。', requestId: '' }
    profileSaved.value = false
    return
  }

  profileSaving.value = true
  profileFailure.value = null
  profileSaved.value = false
  try {
    const updated = parseProfile(runtime.unwrap(await accountApi.PATCH('/api/v1/account', {
      body: {
        display_name: displayName,
        avatar_uri: profileForm.avatarUri.trim() || null,
      },
    })))
    applyProfile(updated)
    if (workspace.tenantIdentity !== null) {
      workspace.tenantIdentity.accountLabel = updated.displayName
    }
    profileSaved.value = true
  } catch (error) {
    profileFailure.value = failureFrom(error, '个人资料保存失败，请稍后重试。')
  } finally {
    profileSaving.value = false
  }
}

const passwordValidationMessage = (): string | null => {
  if (passwordForm.currentPassword === ''
    || passwordForm.newPassword === ''
    || passwordForm.confirmPassword === '') {
    return '请填写当前密码、新密码和确认密码。'
  }
  if (passwordForm.newPassword !== passwordForm.confirmPassword) {
    return '两次输入的新密码不一致。'
  }
  const passwordBytes = new TextEncoder().encode(passwordForm.newPassword).byteLength
  if (passwordBytes < 12 || passwordBytes > 128) {
    return '新密码长度必须为 12 到 128 字节。'
  }
  return null
}

const changePassword = async () => {
  const validationMessage = passwordValidationMessage()
  if (validationMessage !== null) {
    passwordFailure.value = { message: validationMessage, requestId: '' }
    return
  }

  passwordSaving.value = true
  passwordFailure.value = null
  try {
    runtime.unwrap(await accountApi.POST('/api/v1/account/password', {
      body: {
        current_password: passwordForm.currentPassword,
        new_password: passwordForm.newPassword,
      },
    }))
    passwordForm.currentPassword = ''
    passwordForm.newPassword = ''
    passwordForm.confirmPassword = ''
    await runtime.logout('tenant').catch(() => undefined)
    await router.replace('/login')
  } catch (error) {
    passwordFailure.value = failureFrom(error, '密码修改失败，请稍后重试。')
  } finally {
    passwordSaving.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <PageContent>
    <PageHeader>账号信息</PageHeader>
    <el-alert
      v-if="loadFailure"
      :title="loadFailure.message"
      type="error"
      :closable="false"
    >
      <template #default>
        <div v-if="loadFailure.requestId">
          请求编号：{{ loadFailure.requestId }}
        </div>
        <el-button
          data-testid="profile-load-retry"
          native-type="button"
          :disabled="profileLoading"
          @click="loadProfile"
        >
          重新加载个人资料
        </el-button>
      </template>
    </el-alert>
    <el-descriptions
      :column="1"
      border
      class="detail-list"
    >
      <el-descriptions-item label="账号">
        {{ workspace.tenantIdentity?.accountLabel }}
      </el-descriptions-item>
      <el-descriptions-item label="成员身份">
        {{ workspace.tenantIdentity?.actorLabel }}
      </el-descriptions-item>
      <el-descriptions-item label="租户">
        {{ workspace.tenantIdentity?.contextLabel }}
      </el-descriptions-item>
      <el-descriptions-item label="授权版本">
        {{ context.authorizationRevision }}
      </el-descriptions-item>
    </el-descriptions>

    <section
      v-if="profile"
      class="account-section"
    >
      <h2>登录凭证</h2>
      <el-descriptions
        :column="1"
        border
        class="detail-list"
      >
        <el-descriptions-item label="账号 ID">
          {{ profile.accountId }}
        </el-descriptions-item>
        <el-descriptions-item label="凭证">
          {{ profile.credential.identifierMasked }}
        </el-descriptions-item>
        <el-descriptions-item label="类型">
          {{ profile.credential.kind }} / {{ profile.credential.identifierType }}
        </el-descriptions-item>
        <el-descriptions-item label="验证时间">
          {{ profile.credential.verifiedAt ?? '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="密码更新时间">
          {{ profile.credential.secretChangedAt ?? '-' }}
        </el-descriptions-item>
      </el-descriptions>
    </section>

    <div class="account-form-grid">
      <section class="account-section">
        <h2>个人资料</h2>
        <el-alert
          v-if="profileFailure"
          :title="profileFailure.message"
          type="error"
          :closable="false"
        >
          <template
            v-if="profileFailure.requestId"
            #default
          >
            请求编号：{{ profileFailure.requestId }}
          </template>
        </el-alert>
        <el-alert
          v-else-if="profileSaved"
          title="个人资料已保存。"
          type="success"
          :closable="false"
        />
        <el-form
          v-if="profile"
          data-testid="profile-form"
          class="account-form"
          label-position="top"
          @submit.prevent="saveProfile"
        >
          <el-form-item
            label="显示名"
            required
          >
            <el-input
              v-model="profileForm.displayName"
              data-testid="profile-display-name"
              autocomplete="name"
              :disabled="profileLoading"
              @update:model-value="clearProfileSaved"
            />
          </el-form-item>
          <el-form-item label="头像地址">
            <el-input
              v-model="profileForm.avatarUri"
              data-testid="profile-avatar-uri"
              type="url"
              autocomplete="url"
              clearable
              :disabled="profileLoading"
              @update:model-value="clearProfileSaved"
            />
          </el-form-item>
          <el-button
            type="primary"
            native-type="submit"
            :loading="profileSaving"
            :disabled="profileLoading"
          >
            保存资料
          </el-button>
        </el-form>
      </section>

      <section class="account-section">
        <h2>修改密码</h2>
        <el-alert
          v-if="passwordFailure"
          :title="passwordFailure.message"
          type="error"
          :closable="false"
        >
          <template
            v-if="passwordFailure.requestId"
            #default
          >
            请求编号：{{ passwordFailure.requestId }}
          </template>
        </el-alert>
        <el-form
          data-testid="password-form"
          class="account-form"
          label-position="top"
          @submit.prevent="changePassword"
        >
          <el-form-item
            label="当前密码"
            required
          >
            <el-input
              v-model="passwordForm.currentPassword"
              data-testid="current-password"
              type="password"
              autocomplete="current-password"
              show-password
            />
          </el-form-item>
          <el-form-item
            label="新密码"
            required
          >
            <el-input
              v-model="passwordForm.newPassword"
              data-testid="new-password"
              type="password"
              autocomplete="new-password"
              show-password
            />
          </el-form-item>
          <el-form-item
            label="确认新密码"
            required
          >
            <el-input
              v-model="passwordForm.confirmPassword"
              data-testid="confirm-password"
              type="password"
              autocomplete="new-password"
              show-password
            />
          </el-form-item>
          <el-button
            type="primary"
            native-type="submit"
            :loading="passwordSaving"
          >
            修改密码
          </el-button>
        </el-form>
      </section>
    </div>
  </PageContent>
</template>

<style scoped>
.account-section {
  min-width: 0;
  padding-top: 20px;
  border-top: 1px solid var(--pa-shell-border-color);
}

.account-section h2 {
  margin: 0 0 16px;
  font-size: 18px;
  line-height: 1.4;
}

.account-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 32px;
}

.account-form {
  display: grid;
  align-content: start;
  margin-top: 16px;
}

.account-form .el-button {
  justify-self: start;
  min-width: 112px;
}

@media (max-width: 760px) {
  .account-form-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
  }
}
</style>
