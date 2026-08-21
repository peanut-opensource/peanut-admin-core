<script setup lang="ts">
import { PageContent, PageHeader } from '@peanut-admin/admin/shell'
import { usePlatformContext, useTenantContext } from '@peanut-admin/admin/core'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import { useWorkspaceStore } from '../../app/store'

const route = useRoute()
const workspace = useWorkspaceStore()
const tenant = useTenantContext()
const platform = usePlatformContext()
const isTenant = computed(() => route.meta.audience === 'tenant')
const identity = computed(() => isTenant.value ? workspace.tenantIdentity : workspace.platformIdentity)
const metrics = computed(() => isTenant.value
  ? [
      { label: '已开通模块', value: tenant.moduleSet.size },
      { label: '当前权限', value: tenant.permissionSet.size },
      { label: '授权版本', value: tenant.authorizationRevision ?? '-' },
    ]
  : [
      { label: '平台权限', value: platform.permissionSet.size },
      { label: '授权版本', value: platform.authorizationRevision ?? '-' },
      { label: '当前身份', value: identity.value?.actorLabel ?? '-' },
    ])
</script>

<template>
  <PageContent>
    <PageHeader>{{ isTenant ? '工作台' : '平台工作台' }}</PageHeader>
    <section
      class="metric-grid"
      aria-label="当前工作区摘要"
    >
      <article
        v-for="metric in metrics"
        :key="metric.label"
        class="metric-item"
      >
        <span>{{ metric.label }}</span>
        <strong>{{ metric.value }}</strong>
      </article>
    </section>
    <section class="workspace-summary">
      <h2>{{ identity?.contextLabel }}</h2>
      <p>{{ identity?.accountLabel }} · {{ identity?.actorLabel }}</p>
    </section>
  </PageContent>
</template>
