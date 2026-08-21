import { ADMIN_CORE_VERSION } from '@peanut-admin/admin/core'
import { ADMIN_SHELL_VERSION } from '@peanut-admin/admin/shell'
import { describe, expect, it } from 'vitest'

import { exampleGreetingModule } from '../src/modules/example-greeting'

describe('internal starter package composition', () => {
  it('uses versioned package roots and a fictional module contribution', () => {
    expect(ADMIN_CORE_VERSION).toBe('0.1.0')
    expect(ADMIN_SHELL_VERSION).toBe('0.1.0')
    expect(exampleGreetingModule.key).toBe('example.greeting')
    expect(exampleGreetingModule.routes[0]?.access.permissionKeys).toEqual([
      'example.greeting.read',
    ])
  })
})
