import { createTenantLifecycle } from '@peanut-admin/admin/core'

export interface ContextGenerationTicket {
  value: number
  signal: AbortSignal
  isCurrent: () => boolean
}

export interface ContextGeneration {
  current: () => number
  capture: () => ContextGenerationTicket
  advance: () => number
}

export const createContextGeneration = (): ContextGeneration => {
  const lifecycle = createTenantLifecycle()

  return {
    current: lifecycle.current,
    capture: () => {
      const ticket = lifecycle.capture()
      return { value: ticket.generation, signal: ticket.signal, isCurrent: ticket.isCurrent }
    },
    advance: lifecycle.invalidate,
  }
}
