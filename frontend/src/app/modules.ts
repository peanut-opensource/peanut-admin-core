import type { AudienceApiClient } from '@peanut-admin/admin/core'

import { exampleReferenceModule } from '../modules/example-reference'
import { exampleTargetModule } from '../modules/example-target'
import { exampleWorkItemModule } from '../modules/example-work-item'
import { createPeanutFileMediaModule } from '../modules/peanut-file-media'
import { createPeanutImportExportModule } from '../modules/peanut-import-export'
import { createPeanutIntegrationSecurityModule } from '../modules/peanut-integration-security'
import { createPeanutNotificationSmsModule } from '../modules/peanut-notification-sms'
import { createPeanutReferenceCodesModule } from '../modules/peanut-reference-codes'
import { createPeanutSettingsModule } from '../modules/peanut-settings'
import { createPeanutTaskJobModule } from '../modules/peanut-task-job'

export interface AppModuleOptions {
  tenantClient: AudienceApiClient
}

export const createAppModules = (options: AppModuleOptions) => [
  exampleTargetModule,
  exampleReferenceModule,
  exampleWorkItemModule,
  createPeanutSettingsModule({ client: options.tenantClient }),
  createPeanutReferenceCodesModule({ client: options.tenantClient }),
  createPeanutFileMediaModule({ client: options.tenantClient }),
  createPeanutTaskJobModule({ client: options.tenantClient }),
  createPeanutNotificationSmsModule({ client: options.tenantClient }),
  createPeanutImportExportModule({ client: options.tenantClient }),
  createPeanutIntegrationSecurityModule({ client: options.tenantClient }),
] as const
