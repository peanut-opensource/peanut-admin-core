import { describe, expect, it } from 'vitest'

import { createContextGeneration } from '../src/app/context-generation'

describe('context generation', () => {
  it('rejects a late response after tenant state changes', () => {
    const generation = createContextGeneration()
    const oldRequest = generation.capture()

    generation.advance()

    expect(oldRequest.isCurrent()).toBe(false)
    expect(oldRequest.signal.aborted).toBe(true)
    expect(generation.capture().isCurrent()).toBe(true)
  })
})
