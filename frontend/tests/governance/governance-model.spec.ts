import { describe, expect, it } from 'vitest'

import { createGovernanceIconPresentation } from '../../src/features/governance/icon'
import { createGovernanceWorkbenchModel } from '../../src/features/governance/model'
import { resolveGovernanceRoleSelection } from '../../src/features/governance/role-selection'

describe('governance workbench feature model', () => {
  it('keeps tenant and platform workbench state separate and exposes safe audit fields', () => {
    const tenant = createGovernanceWorkbenchModel('tenant')
    tenant.setRoleDraft({ roleId: '7', revision: 3, permissionKeys: ['core.role.read'] })
    tenant.setAuditDetail({
      id: '1',
      audience: 'tenant',
      eventType: 'tenant.role.updated',
      action: 'core.role.update',
      outcome: 'success',
      requestId: 'req_test',
      occurredAt: '2026-07-24T00:00:00.000Z',
      metadata: { revision: 3 },
    })

    expect(tenant.snapshot()).toMatchObject({ audience: 'tenant', roleDraft: { roleId: '7' } })
    const platform = createGovernanceWorkbenchModel('platform')
    platform.setAuditDetail({
      id: '2', audience: 'platform', eventType: 'governance.role.updated', action: 'platform.role.update', outcome: 'success', requestId: 'req_platform', occurredAt: '2026-07-24T00:00:00.000Z', metadata: {},
    })
    expect(platform.snapshot()).toMatchObject({ audience: 'platform', auditDetail: { id: '2' } })
    expect(() => tenant.setAuditDetail({
      id: '2', audience: 'platform', eventType: 'tenant.role.updated', action: 'platform.role.update', outcome: 'success', requestId: 'req_platform', occurredAt: '2026-07-24T00:00:00.000Z', metadata: {},
    })).toThrow('GOVERNANCE_AUDIENCE_MISMATCH')
  })

  it('presents only the resolved registry glyph with an accessible label', () => {
    expect(createGovernanceIconPresentation('S', 'Roles')).toEqual({
      glyph: 'S',
      label: 'Roles',
      role: 'img',
    })
    expect(() => createGovernanceIconPresentation('', 'Roles')).toThrow('GOVERNANCE_ICON_INVALID')
  })

  it('loads the complete platform role before allowing an existing permission set to be saved', async () => {
    const loaded: string[] = []
    const selection = await resolveGovernanceRoleSelection(
      'platform',
      '7',
      { id: '7', revision: '4', permission_count: 2 },
      async roleId => {
        loaded.push(roleId)
        return {
          id: '7',
          revision: '4',
          permission_keys: ['platform.audit.read', 'platform.role.read'],
        }
      },
    )

    expect(loaded).toEqual(['7'])
    expect(selection.permissionKeys).toEqual(['platform.audit.read', 'platform.role.read'])
    await expect(resolveGovernanceRoleSelection(
      'platform',
      '7',
      { id: '7', revision: '4', permission_count: 2 },
      async () => ({ id: '7', revision: '4' }),
    )).rejects.toThrow('GOVERNANCE_ROLE_SNAPSHOT_INCOMPLETE')
  })
})
