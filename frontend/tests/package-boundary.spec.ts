import { describe, expect, it } from 'vitest'

import { ADMIN_CORE_PACKAGE } from '@peanut-admin/admin/core'
import { ADMIN_SHELL_PACKAGE } from '@peanut-admin/admin/shell'

describe('reference admin package boundary', () => {
  it('consumes only public package exports', () => {
    expect(ADMIN_CORE_PACKAGE).toBe('@peanut-admin/admin/core')
    expect(ADMIN_SHELL_PACKAGE).toBe('@peanut-admin/admin/shell')
  })
})
