<script setup lang="ts">
import { hasPermission, usePlatformContext } from '@peanut-admin/admin/core'
import { createOpsConsoleRuntime, OPS_BACKUP_PERMISSION, OPS_LOGS_PERMISSION, OPS_MAINTENANCE_PERMISSION, OPS_READ_PERMISSION, OPS_RESTORE_PERMISSION, OpsConsolePage, opsConsoleRuntimeKey } from '@peanut-admin/admin/ops-console'
import type { OpsConsoleTransport, OpsTransportResult } from '@peanut-admin/admin/ops-console'
import { onBeforeUnmount, provide } from 'vue'
import { useAdminRuntime } from '../../app/runtime'

interface ApiResult{readonly data?:unknown;readonly error?:unknown;readonly response:Response}
const result=(v:ApiResult):OpsTransportResult=>({body:v.response.ok?v.data:v.error,headers:v.response.headers,status:v.response.status})
const client=()=>useAdminRuntime().platformClient
const transport:OpsConsoleTransport={
  async overview(signal){return result(await client().GET('/api/platform/v1/ops/status',{signal}))},
  async maintenance(signal){return result(await client().GET('/api/platform/v1/ops/maintenance',{signal}))},
  async submitBackup(providerKey,idempotencyKey,signal){return result(await client().POST('/api/platform/v1/ops/tasks/backup',{params:{header:{'Idempotency-Key':idempotencyKey}},body:{provider_key:providerKey},signal}))},
  async submitRestore(providerKey,backupReferenceKey,targetKey,idempotencyKey,signal){return result(await client().POST('/api/platform/v1/ops/tasks/restore',{params:{header:{'Idempotency-Key':idempotencyKey}},body:{provider_key:providerKey,backup_reference_key:backupReferenceKey,target_key:targetKey},signal}))},
  async task(taskKey,signal){return result(await client().GET('/api/platform/v1/ops/tasks/{task_key}',{params:{path:{task_key:taskKey}},signal}))},
  async scheduleMaintenance(input,expectedRevision,idempotencyKey,signal){return result(await client().PUT('/api/platform/v1/ops/maintenance',{params:{header:{'If-Match':`\"rev-${expectedRevision}\"`,'Idempotency-Key':idempotencyKey}},body:{reason_key:input.reasonKey,starts_at:input.startsAt,ends_at:input.endsAt},signal}))},
  async closeMaintenance(maintenanceKey,revision,idempotencyKey,signal){return result(await client().POST('/api/platform/v1/ops/maintenance/{maintenance_key}/close',{params:{path:{maintenance_key:maintenanceKey},header:{'If-Match':`"rev-${revision}"`,'Idempotency-Key':idempotencyKey}},signal}))},
  async logs(source,severity,cursor,pageSize,signal){return result(await client().GET('/api/platform/v1/ops/logs',{params:{query:{source,severity,cursor,page_size:pageSize}},signal}))},
}
const allowed=(key:string)=>()=>hasPermission(usePlatformContext().permissionSet,key)
const runtime=createOpsConsoleRuntime({transport,providers:[{key:'reference.mysql',backup:true,restoreTargets:['verification']}],maintenanceReasons:['planned-upgrade','database-maintenance','security-maintenance'],logSources:['platform.audit'],canRead:allowed(OPS_READ_PERMISSION),canBackup:allowed(OPS_BACKUP_PERMISSION),canRestore:allowed(OPS_RESTORE_PERMISSION),canMaintain:allowed(OPS_MAINTENANCE_PERMISSION),canReadLogs:allowed(OPS_LOGS_PERMISSION)})
provide(opsConsoleRuntimeKey,runtime);onBeforeUnmount(runtime.dispose)
</script>
<template><OpsConsolePage /></template>
