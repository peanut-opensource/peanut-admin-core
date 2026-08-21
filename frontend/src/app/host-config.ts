import { defineAdminHostConfig } from '@peanut-admin/admin/core'
import type { AdminHostConfig } from '@peanut-admin/admin/core'

export type AdminHostEnvironment = Readonly<Record<string, string | boolean | undefined>>

const configuredValue = (environment: AdminHostEnvironment, key: string): string | null => {
  const value = environment[key]
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

const requiredBrowserOrigin = (browserOrigin: string | undefined): string => {
  if (browserOrigin === undefined || browserOrigin === '' || browserOrigin === 'null') {
    throw new Error('ADMIN_HOST_ORIGIN_INVALID')
  }
  return browserOrigin
}

export const readAdminHostConfig = (
  environment: AdminHostEnvironment = import.meta.env,
  browserOrigin: string | undefined = globalThis.location?.origin,
): AdminHostConfig => {
  const fallbackOrigin = requiredBrowserOrigin(browserOrigin)
  const sharedBaseUrl = configuredValue(environment, 'VITE_API_BASE_URL')
  const tenantBaseUrl = configuredValue(environment, 'VITE_TENANT_API_BASE_URL') ?? sharedBaseUrl ?? fallbackOrigin
  const platformBaseUrl = configuredValue(environment, 'VITE_PLATFORM_API_BASE_URL') ?? sharedBaseUrl ?? fallbackOrigin

  return defineAdminHostConfig({
    tenant: {
      baseUrl: tenantBaseUrl,
      allowedOrigin: configuredValue(environment, 'VITE_TENANT_API_ALLOWED_ORIGIN') ?? tenantBaseUrl,
      clientKey: configuredValue(environment, 'VITE_TENANT_CLIENT_KEY') ?? 'admin-web',
    },
    platform: {
      baseUrl: platformBaseUrl,
      allowedOrigin: configuredValue(environment, 'VITE_PLATFORM_API_ALLOWED_ORIGIN') ?? platformBaseUrl,
      clientKey: configuredValue(environment, 'VITE_PLATFORM_CLIENT_KEY') ?? 'platform-web',
    },
  })
}
