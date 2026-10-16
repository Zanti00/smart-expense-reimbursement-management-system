import { describe, it, expect } from 'vitest'
import { cn } from './cn'

describe('cn utility helper', () => {
  it('merges class names correctly', () => {
    expect(cn('px-2 py-1', 'bg-primary')).toBe('px-2 py-1 bg-primary')
  })

  it('resolves Tailwind class conflicts in favor of the latter class', () => {
    expect(cn('p-4', 'p-6')).toBe('p-6')
    expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500')
  })

  it('handles conditional falsy values cleanly', () => {
    expect(cn('btn', false && 'btn-active', null, undefined, 'text-sm')).toBe('btn text-sm')
  })
})
