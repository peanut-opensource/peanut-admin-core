import {
  createNotificationFetchTransport,
  createNotificationModuleContribution,
  createNotificationRuntime,
} from '@peanut-admin/admin/notification-sms'
import type { NotificationRuntime } from '@peanut-admin/admin/notification-sms'
import type { AdminModuleContribution } from '@peanut-admin/admin/core'

export interface PeanutNotificationSmsHostOptions {
  baseUrl: string
  fetch: (request: Request) => Promise<Response>
  canRead: () => boolean
}

export interface PeanutNotificationSmsHost { module: AdminModuleContribution; runtime: NotificationRuntime }

export const createPeanutNotificationSmsHost = (options: PeanutNotificationSmsHostOptions): PeanutNotificationSmsHost => {
  const runtime = createNotificationRuntime({
    transport: createNotificationFetchTransport({ baseUrl: options.baseUrl, fetch: options.fetch }),
    canRead: options.canRead,
  })
  return { module: createNotificationModuleContribution(runtime), runtime }
}
