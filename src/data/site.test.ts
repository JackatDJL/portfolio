import { describe, expect, it } from 'vitest'
import { publicProjects } from './site'

describe('public project data', () => {
  it('redacts confidential projects to the safe public shape', () => {
    const confidential = publicProjects.find(
      (project) => project.status === 'Confidential',
    )
    expect(confidential).toEqual({
      id: 'confidential-local-first',
      slug: 'confidential-local-first',
      name: 'Confidential local-first technology project',
      kind: 'Software',
      status: 'Confidential',
      summary:
        'A confidential project. Public details are intentionally limited.',
      year: 'Current',
      roles: ['Project work'],
      technologies: [],
      tags: ['technology'],
    })
  })
})
