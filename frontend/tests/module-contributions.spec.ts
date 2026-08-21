import { describe, expect, it } from 'vitest'

import { exampleReferenceModule } from '../src/modules/example-reference'
import { exampleTargetModule } from '../src/modules/example-target'
import { exampleWorkItemModule } from '../src/modules/example-work-item'

describe('build-time module contributions', () => {
  it('keeps module routes lazy, unique, and inside the tenant workspace', () => {
    const modules = [exampleTargetModule, exampleReferenceModule, exampleWorkItemModule]
    const names = modules.flatMap(module => module.routes.map(route => route.name))

    expect(new Set(names).size).toBe(names.length)
    expect(modules.flatMap(module => module.routes).every(route => route.path.startsWith('/app/'))).toBe(true)
    expect(modules.every(module => module.disposeOnTenantChange)).toBe(true)
    expect(exampleReferenceModule.routes[0]?.access.permissionKeys).toContain('example.reference.use')
  })
})
