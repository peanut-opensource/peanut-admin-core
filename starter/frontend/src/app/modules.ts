import { exampleGreetingModule } from '../modules/example-greeting'
import { createPeanutReferenceCodesHost } from '../modules/peanut-reference-codes'
import type { PeanutReferenceCodesHostOptions } from '../modules/peanut-reference-codes'
import { createPeanutSettingsHost } from '../modules/peanut-settings'
import type { PeanutSettingsHostOptions } from '../modules/peanut-settings'
import { createPeanutFileMediaHost } from '../modules/peanut-file-media'
import type { PeanutFileMediaHostOptions } from '../modules/peanut-file-media'
import { createPeanutTaskJobHost } from '../modules/peanut-task-job'
import type { PeanutTaskJobHostOptions } from '../modules/peanut-task-job'
import { createPeanutNotificationSmsHost } from '../modules/peanut-notification-sms'
import type { PeanutNotificationSmsHostOptions } from '../modules/peanut-notification-sms'
import { createPeanutImportExportHost } from '../modules/peanut-import-export'
import type { PeanutImportExportHostOptions } from '../modules/peanut-import-export'
import { createPeanutIntegrationSecurityHost } from '../modules/peanut-integration-security'
import type { PeanutIntegrationSecurityHostOptions } from '../modules/peanut-integration-security'
import { createPeanutOpsConsoleHost } from '../modules/peanut-ops-console'
import type { PeanutOpsConsoleHostOptions } from '../modules/peanut-ops-console'

export type StarterModuleOptions = PeanutSettingsHostOptions & PeanutReferenceCodesHostOptions & PeanutFileMediaHostOptions & PeanutTaskJobHostOptions & PeanutNotificationSmsHostOptions & PeanutImportExportHostOptions & PeanutIntegrationSecurityHostOptions & PeanutOpsConsoleHostOptions

export const createStarterModules = (options: StarterModuleOptions) => {
  const settings = createPeanutSettingsHost(options)
  const referenceCodes = createPeanutReferenceCodesHost(options)
  const fileMedia = createPeanutFileMediaHost(options)
  const taskJob = createPeanutTaskJobHost(options)
  const notificationSms = createPeanutNotificationSmsHost(options)
  const importExport = createPeanutImportExportHost(options)
  const integrationSecurity = createPeanutIntegrationSecurityHost(options)
  const opsConsole = createPeanutOpsConsoleHost(options)

  return {
    modules: [exampleGreetingModule, settings.module, referenceCodes.module, fileMedia.module, taskJob.module, notificationSms.module, importExport.module, integrationSecurity.module] as const,
    settingsModule: settings.module,
    settingsRuntime: settings.runtime,
    referenceCodesModule: referenceCodes.module,
    referenceCodesRuntime: referenceCodes.runtime,
    fileMediaModule: fileMedia.module,
    fileMediaRuntime: fileMedia.runtime,
    taskJobModule: taskJob.module,
    taskJobRuntime: taskJob.runtime,
    notificationSmsModule: notificationSms.module,
    notificationSmsRuntime: notificationSms.runtime,
    importExportModule: importExport.module,
    importExportRuntime: importExport.runtime,
    integrationSecurityModule: integrationSecurity.module,
    integrationSecurityRuntime: integrationSecurity.runtime,
    opsConsoleRoute: opsConsole.route,
    opsConsoleRuntime: opsConsole.runtime,
  }
}
