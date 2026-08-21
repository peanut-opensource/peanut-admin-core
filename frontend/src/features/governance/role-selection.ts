export type GovernanceRoleAudience = 'tenant' | 'platform'

export interface GovernanceRoleSelection {
  role: Record<string, unknown>
  roleId: string
  revision: number
  permissionKeys: string[]
}

const isRecord = (value: unknown): value is Record<string, unknown> => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
)

export const resolveGovernanceRoleSelection = async (
  audience: GovernanceRoleAudience,
  roleId: string,
  listedRole: unknown,
  loadPlatformRole: (roleId: string) => Promise<unknown>,
): Promise<GovernanceRoleSelection> => {
  const value = audience === 'platform' ? await loadPlatformRole(roleId) : listedRole
  if (!isRecord(value) || String(value.id ?? '') !== roleId) {
    throw new Error('GOVERNANCE_ROLE_SNAPSHOT_INVALID')
  }
  const revisionText = String(value.revision ?? '')
  const revision = Number.parseInt(revisionText, 10)
  const permissionKeys = value.permission_keys
  if (!/^[1-9][0-9]*$/.test(revisionText)
    || !Array.isArray(permissionKeys)
    || permissionKeys.some(key => typeof key !== 'string' || key === '')) {
    throw new Error('GOVERNANCE_ROLE_SNAPSHOT_INCOMPLETE')
  }

  return {
    role: value,
    roleId,
    revision,
    permissionKeys: [...new Set(permissionKeys)].sort(),
  }
}
