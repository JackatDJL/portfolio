export const site = {
  name: 'Jack Ruder',
  url: 'https://jack.djl.foundation',
  description:
    'Jack Ruder builds projects around technology, music, education, and civic life.',
  profile: {
    name: 'Jack Ruder',
    role: 'Student, developer, and project builder',
    location: 'TODO: add location',
    bio: 'TODO: add a concise first-person biography.',
    email: 'TODO: add public contact email',
  },
  links: [
    { label: 'GitHub', href: 'TODO: add GitHub URL' },
    { label: 'LinkedIn', href: 'TODO: add LinkedIn URL' },
  ],
  now: {
    updatedAt: '2026-09-04',
    note: 'TODO: replace this with a short, dated update about current work.',
    items: [
      'Developing software and technical projects.',
      'Making and performing music.',
      'Contributing to education and civic work.',
    ],
  },
} as const

export type ProjectStatus =
  'Active' | 'Maintained' | 'Paused' | 'Archived' | 'Confidential'

export type Project = {
  id: string
  slug: string
  name: string
  kind: 'Software' | 'Robotics' | 'Education' | 'Civic' | 'Event' | 'Experiment'
  status: ProjectStatus
  summary: string
  description?: string
  year: string
  roles: string[]
  technologies: string[]
  links?: { label: string; href: string }[]
  image?: { src: string; alt: string }
  media?: { label: string; href: string }[]
  tags: string[]
}

// This is public-safe data. Never place a confidential project's private name,
// client, repository, image, or internal details in this repository.
export const projects: Project[] = [
  {
    id: 'project-placeholder',
    slug: 'project-placeholder',
    name: 'Project archive entry',
    kind: 'Software',
    status: 'Active',
    summary: 'TODO: describe a public project in one factual sentence.',
    description: 'TODO: add the problem, your role, and the result.',
    year: 'TODO',
    roles: ['TODO: add role'],
    technologies: ['TODO: add technologies'],
    tags: ['technology'],
  },
  {
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
  },
]

export type Experience = {
  id: string
  title: string
  organisation: string
  start: string
  end?: string
  description: string
  highlights: string[]
  tags: string[]
}

export const experience: Experience[] = [
  {
    id: 'experience-placeholder',
    title: 'TODO: role title',
    organisation: 'TODO: organisation',
    start: 'TODO',
    description: 'TODO: add a factual description of this experience.',
    highlights: ['TODO: add a specific contribution or outcome.'],
    tags: ['technology', 'leadership'],
  },
]

export const education = [
  {
    id: 'education-placeholder',
    institution: 'TODO: school or institution',
    programme: 'TODO: programme or qualification',
    start: 'TODO',
    end: 'TODO',
    note: 'TODO: add a relevant focus or achievement.',
  },
]

export const engagement = [
  {
    id: 'engagement-placeholder',
    title: 'TODO: engagement or voluntary role',
    organisation: 'TODO: organisation',
    note: 'TODO: add a public, factual description.',
  },
]

export const skills = [
  {
    category: 'Build',
    items: ['Software projects', 'Open source', 'Systems thinking'],
  },
  {
    category: 'Work with people',
    items: ['Project leadership', 'Public communication', 'Collaboration'],
  },
  {
    category: 'Music',
    items: ['Performance', 'Music technology', 'Instrument curiosity'],
  },
] as const

export const music = [
  {
    id: 'music-placeholder',
    title: 'TODO: ensemble, instrument, or musical work',
    note: 'TODO: add a factual music entry.',
  },
]

export const awards = [
  {
    id: 'award-placeholder',
    title: 'TODO: award or recognition',
    year: 'TODO',
  },
]

export const writing = [
  {
    slug: 'notes-from-the-archive',
    title: 'Notes from the archive',
    date: '2026-09-04',
    summary: 'TODO: replace with a published essay or remove this entry.',
    tags: ['notes'],
    body: 'TODO: Write the article in Markdown or MDX. This placeholder makes the content path visible without inventing a point of view.',
  },
]

export const publicProjects = projects.map((project) =>
  project.status === 'Confidential'
    ? {
        id: project.id,
        slug: project.slug,
        name: 'Confidential local-first technology project',
        kind: project.kind,
        status: project.status,
        summary:
          'A confidential project. Public details are intentionally limited.',
        year: project.year,
        roles: ['Project work'],
        technologies: [],
        tags: ['technology'],
      }
    : project,
)

export type CvProfileId =
  | 'general'
  | 'technology'
  | 'music'
  | 'management'
  | 'media'
  | 'entrepreneurship'

type CvSection =
  | 'experience'
  | 'education'
  | 'projects'
  | 'skills'
  | 'music'
  | 'engagement'
  | 'awards'

export type CvProfile = {
  id: CvProfileId
  title: string
  intro: string
  sections: CvSection[]
  featuredProjectTags: string[]
  featuredExperienceTags: string[]
}

export const cvProfiles: Record<CvProfileId, CvProfile> = {
  general: {
    id: 'general',
    title: 'Curriculum vitae',
    intro: 'TODO: write a concise, general CV introduction.',
    sections: [
      'experience',
      'education',
      'projects',
      'skills',
      'music',
      'engagement',
      'awards',
    ],
    featuredProjectTags: [],
    featuredExperienceTags: [],
  },
  technology: {
    id: 'technology',
    title: 'Technology profile',
    intro:
      'A profile focused on software, systems thinking, and technical project work.',
    sections: [
      'experience',
      'projects',
      'skills',
      'education',
      'engagement',
      'music',
      'awards',
    ],
    featuredProjectTags: ['technology', 'robotics', 'open-source'],
    featuredExperienceTags: ['technology', 'leadership'],
  },
  music: {
    id: 'music',
    title: 'Music profile',
    intro:
      'A profile focused on music, instruments, technical curiosity, and project work.',
    sections: [
      'music',
      'experience',
      'projects',
      'skills',
      'education',
      'engagement',
      'awards',
    ],
    featuredProjectTags: ['music', 'technology'],
    featuredExperienceTags: ['music'],
  },
  management: {
    id: 'management',
    title: 'Management profile',
    intro:
      'A profile focused on project leadership, collaboration, and delivery.',
    sections: [
      'experience',
      'projects',
      'engagement',
      'skills',
      'education',
      'music',
      'awards',
    ],
    featuredProjectTags: ['leadership', 'civic'],
    featuredExperienceTags: ['leadership'],
  },
  media: {
    id: 'media',
    title: 'Media profile',
    intro:
      'A profile focused on communications, live production interest, technology, and public communication.',
    sections: [
      'experience',
      'projects',
      'engagement',
      'skills',
      'music',
      'education',
      'awards',
    ],
    featuredProjectTags: ['media', 'event', 'technology'],
    featuredExperienceTags: ['media', 'communication'],
  },
  entrepreneurship: {
    id: 'entrepreneurship',
    title: 'Entrepreneurship profile',
    intro:
      'A profile focused on project development, business thinking, civic engagement, and leadership.',
    sections: [
      'projects',
      'experience',
      'engagement',
      'skills',
      'education',
      'music',
      'awards',
    ],
    featuredProjectTags: ['entrepreneurship', 'civic', 'leadership'],
    featuredExperienceTags: ['leadership'],
  },
}

export const cvAliases = {
  airbus: { profile: 'technology', title: 'Technology and systems profile' },
  steinway: { profile: 'music', title: 'Music, craft, and technology profile' },
  yamaha: { profile: 'music', title: 'Music, technology, and project profile' },
  ndr: { profile: 'media', title: 'Media and technology profile' },
  ihk: {
    profile: 'entrepreneurship',
    title: 'Entrepreneurship and leadership profile',
  },
} as const satisfies Record<string, { profile: CvProfileId; title: string }>

export function resolveCvProfile(slug?: string) {
  const alias =
    slug && slug in cvAliases
      ? cvAliases[slug as keyof typeof cvAliases]
      : undefined
  const profileId =
    alias?.profile ??
    (slug && slug in cvProfiles ? (slug as CvProfileId) : 'general')
  const profile = cvProfiles[profileId]
  return {
    ...profile,
    title: alias?.title ?? profile.title,
    alias: alias ? slug : undefined,
  }
}

export function selectProjects(tags: string[]) {
  const matching = publicProjects.filter(
    (project) =>
      tags.length === 0 || project.tags.some((tag) => tags.includes(tag)),
  )
  return matching.length > 0 ? matching : publicProjects
}

export function selectExperience(tags: string[]) {
  const matching = experience.filter(
    (entry) =>
      tags.length === 0 || entry.tags.some((tag) => tags.includes(tag)),
  )
  return matching.length > 0 ? matching : experience
}
