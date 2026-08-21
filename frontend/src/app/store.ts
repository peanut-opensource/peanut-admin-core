import { defineStore } from 'pinia'
import type { ApiAudience, ProblemDetails } from '@peanut-admin/admin/core'

import type { AdminMenuItem, TenantLoginSelection } from './contracts'

export interface WorkspaceIdentity {
  accountLabel: string
  contextLabel: string
  actorLabel: string
}

interface WorkspaceState {
  activeAudience: ApiAudience | null
  tenantIdentity: WorkspaceIdentity | null
  platformIdentity: WorkspaceIdentity | null
  tenantMenus: AdminMenuItem[]
  platformMenus: AdminMenuItem[]
  tenantMenuRevision: string | null
  platformMenuRevision: string | null
  tenantSelection: TenantLoginSelection | null
  booting: boolean
  shellCollapsed: boolean
  mobileNavigationOpen: boolean
  problem: ProblemDetails | null
  menuDiagnostics: string[]
}

export const useWorkspaceStore = defineStore('peanut-admin-reference-workspace', {
  state: (): WorkspaceState => ({
    activeAudience: null,
    tenantIdentity: null,
    platformIdentity: null,
    tenantMenus: [],
    platformMenus: [],
    tenantMenuRevision: null,
    platformMenuRevision: null,
    tenantSelection: null,
    booting: false,
    shellCollapsed: false,
    mobileNavigationOpen: false,
    problem: null,
    menuDiagnostics: [],
  }),
  actions: {
    clearTenant(): void {
      this.tenantIdentity = null
      this.tenantMenus = []
      this.tenantMenuRevision = null
      this.tenantSelection = null
      this.menuDiagnostics = []
    },
    clearPlatform(): void {
      this.platformIdentity = null
      this.platformMenus = []
      this.platformMenuRevision = null
    },
    setProblem(problem: ProblemDetails | null): void {
      this.problem = problem
    },
    addMenuDiagnostic(routeName: string): void {
      if (!this.menuDiagnostics.includes(routeName)) this.menuDiagnostics.push(routeName)
    },
  },
})
