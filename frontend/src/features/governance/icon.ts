export interface GovernanceIconPresentation {
  glyph: string
  label: string
  role: 'img'
}

export const createGovernanceIconPresentation = (
  glyph: string,
  label: string,
): GovernanceIconPresentation => {
  if (glyph.trim() === '' || label.trim() === '') throw new Error('GOVERNANCE_ICON_INVALID')

  return {
    glyph,
    label,
    role: 'img',
  }
}
