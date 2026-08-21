import 'vue-router'
import type { ApiAudience } from '@peanut-admin/admin/core'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    audience?: ApiAudience
    publicAudience?: ApiAudience
    permission?: string
    permissions?: readonly string[]
    moduleKey?: string
    resourcePage?: string
  }
}

export {}
