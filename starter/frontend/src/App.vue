<script setup lang="ts">
import { ADMIN_CORE_PACKAGE } from '@peanut-admin/admin/core'
import { AdminShell, PageContent, PageHeader, ShellHeader, ShellSidebar } from '@peanut-admin/admin/shell'

import { exampleGreetingModule } from './modules/example-greeting'
</script>

<template>
  <AdminShell class="starter-shell">
    <template #header>
      <ShellHeader>Peanut Admin</ShellHeader>
    </template>
    <template #sidebar>
      <ShellSidebar>Internal starter</ShellSidebar>
    </template>
    <PageHeader>Fictional Module</PageHeader>
    <PageContent>
      <p data-testid="module-key">
        {{ exampleGreetingModule.key }}
      </p>
      <p data-testid="package-name">
        {{ ADMIN_CORE_PACKAGE }}
      </p>
    </PageContent>
  </AdminShell>
</template>
