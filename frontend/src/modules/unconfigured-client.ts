import type { AudienceApiClient } from '@peanut-admin/admin/core'

export const UNCONFIGURED_TENANT_CLIENT = new Proxy({} as AudienceApiClient, {
  get() {
    return () => {
      throw new Error('PEANUT_MODULE_CLIENT_NOT_CONFIGURED')
    }
  },
})
