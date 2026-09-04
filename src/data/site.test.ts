import { describe, expect, it } from 'vitest'
import {
  getCvProfileForRoute,
  isCvProfileSlug,
  publicProjects,
  resolveCvProfile,
  selectProjects,
} from './site'

describe('public project data', () => {
  it('redacts confidential projects to the safe public shape', () => {
    const confidential = publicProjects.find(
      (project) => project.status === 'Confidential',
    )
    expect(confidential).toEqual({
      id: 'confidential-technology-project',
      slug: 'confidential-technology-project',
      name: 'Confidential technology project',
      kind: 'Software',
      status: 'Confidential',
      summary:
        'Aktives vertrauliches Projekt. Öffentliche Details bleiben bewusst begrenzt.',
      year: 'seit 2026',
      roles: ['Project work'],
      technologies: [],
      tags: ['technology'],
    })
  })
})

describe('CV profiles', () => {
  it('resolves a public profile from its route slug', () => {
    const profile = resolveCvProfile('technology')

    expect(isCvProfileSlug('technology')).toBe(true)
    expect(profile.slug).toBe('technology')
    expect(profile.sectionOrder).toContain('projects')
    expect(profile.featuredProjects).toEqual([
      'prtop',
      'printgate',
      'ai-ctx',
      'atheblues',
    ])
  })

  it('resolves an employer route as an override of its base profile', () => {
    const profile = resolveCvProfile('yamaha')

    expect(isCvProfileSlug('yamaha')).toBe(true)
    expect(profile.slug).toBe('music')
    expect(profile.alias).toBe('yamaha')
    expect(profile.optionalEmployer).toBe('Yamaha')
    expect(profile.skillsFocus).toEqual(['Music', 'Work with people'])
  })

  it('keeps an unknown slug out of routing and falls back safely in the resolver', () => {
    expect(isCvProfileSlug('not-a-profile')).toBe(false)
    expect(getCvProfileForRoute('not-a-profile')).toBeUndefined()
    expect(resolveCvProfile('not-a-profile').slug).toBe('general')
  })

  it('does not select confidential projects for CV content', () => {
    expect(selectProjects(['confidential-technology-project'])).toEqual([])
  })
})
