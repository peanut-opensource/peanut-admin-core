import { describe, expect, it } from 'vitest'

import { readAdminHostConfig } from '../src/app/host-config'

describe('reference host configuration', () => {
  it('builds independent audience configuration from host-owned values', () => {
    expect(readAdminHostConfig({
      VITE_TENANT_API_BASE_URL: 'https://tenant-api.example.test',
      VITE_TENANT_API_ALLOWED_ORIGIN: 'https://tenant-api.example.test',
      VITE_TENANT_CLIENT_KEY: 'customer-admin',
      VITE_PLATFORM_API_BASE_URL: 'https://platform-api.example.test',
      VITE_PLATFORM_API_ALLOWED_ORIGIN: 'https://platform-api.example.test',
      VITE_PLATFORM_CLIENT_KEY: 'control-admin',
    }, 'https://admin.example.test')).toEqual({
      tenant: {
        baseUrl: 'https://tenant-api.example.test',
        allowedOrigin: 'https://tenant-api.example.test',
        clientKey: 'customer-admin',
      },
      platform: {
        baseUrl: 'https://platform-api.example.test',
        allowedOrigin: 'https://platform-api.example.test',
        clientKey: 'control-admin',
      },
    })
  })

  it('uses the browser origin without adding Tenant or authorization policy', () => {
    const config = readAdminHostConfig({}, 'https://admin.example.test')

    expect(config.tenant).toEqual({
      baseUrl: 'https://admin.example.test',
      allowedOrigin: 'https://admin.example.test',
      clientKey: 'admin-web',
    })
    expect(config.platform).toEqual({
      baseUrl: 'https://admin.example.test',
      allowedOrigin: 'https://admin.example.test',
      clientKey: 'platform-web',
    })
    expect(config).not.toHaveProperty('tenantId')
    expect(config).not.toHaveProperty('permissions')
  })
})
