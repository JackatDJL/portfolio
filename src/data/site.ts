export const site = {
  name: 'Jack Ruder',
  url: 'https://jack.djl.foundation',
  description:
    'Jack Ruder builds projects around technology, music, education, and civic life.',
  profile: {
    name: 'Jack Ruder',
    role: 'Student, developer, musician',
    location: 'Stade, Germany',
    bio: 'Schüler aus Stade, Entwickler, Musiker und Projektbauer. Ich baue Software, organisiere Projekte und beschäftige mich mit technischen, organisatorischen und gesellschaftlichen Systemen.',
    email: undefined as string | undefined,
  },
  home: {
    greeting: 'Moin, ich bin Jack.',
    introduction:
      'Ich bin Student und baue Software. Dazu kommen Musik, Technik, Bildung sowie kommunale und politische Arbeit.',
    archiveNote:
      'Hier landen Projekte, Notizen und Dinge, an denen ich gearbeitet habe. Auch abgebrochene Versuche bleiben Teil der Chronik.',
  },
  links: [{ label: 'GitHub', href: 'https://github.com/JackatDJL' }],
  now: {
    updatedAt: '2026-09-04',
    note: 'Eine Momentaufnahme vom 4. September 2026.',
    items: [
      'prtop, ein Terminal-Werkzeug für Pull Requests und Merge Requests weiterbauen.',
      'Printgate als kleines Laravel- und Heimnetz-Projekt ausprobieren.',
      'Interne technische Arbeit bei Volt Europa mitbetreuen, ohne interne Projekte öffentlich zu machen.',
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
  featured?: boolean
}

// This is public-safe data. Never place a confidential project's private name,
// client, repository, image, or internal details in this repository.
export const projects: Project[] = [
  {
    id: 'prtop',
    slug: 'prtop',
    name: 'prtop',
    kind: 'Software',
    status: 'Active',
    summary:
      'Terminal UI für Pull Requests und Merge Requests über mehrere Git-Plattformen.',
    description:
      'Ein gemeinsames Werkzeug für GitHub, GitLab und Forgejo beziehungsweise Codeberg.',
    year: 'seit August 2026',
    roles: ['Entwicklung'],
    technologies: ['Rust', 'Terminal UI'],
    links: [{ label: 'GitHub', href: 'https://github.com/JackatDJL/prtop' }],
    tags: ['technology', 'open-source', 'developer-tools'],
    featured: true,
  },
  {
    id: 'printgate',
    slug: 'printgate',
    name: 'Printgate',
    kind: 'Software',
    status: 'Active',
    summary:
      'Kleiner Dienst, um einen Drucker im Heimnetz privat aus der Ferne nutzbar zu machen.',
    description:
      'Printgate nutzt CUPS auf dem lokalen Host und ist für einen privaten Zugang über Tailscale gedacht.',
    year: 'seit August 2026',
    roles: ['Entwicklung'],
    technologies: ['Laravel', 'CUPS', 'Tailscale'],
    links: [
      { label: 'GitHub', href: 'https://github.com/JackatDJL/printgate' },
    ],
    tags: ['technology', 'infrastructure'],
    featured: true,
  },
  {
    id: 'erstwaehlerforum-stade',
    slug: 'erstwaehlerforum-stade',
    name: 'Erstwählerforum Stade',
    kind: 'Civic',
    status: 'Archived',
    summary: 'Politisches Bildungsprojekt für junge Erstwähler in Stade.',
    description:
      'Jack initiierte das Forum. Die Veranstaltung fand am 2. Juni 2026 statt.',
    year: '2025 bis 2026',
    roles: ['Initiator', 'Gründer'],
    technologies: [],
    tags: ['civic', 'education', 'leadership'],
    featured: true,
  },
  {
    id: 'atheblues',
    slug: 'atheblues',
    name: 'AtheBlues',
    kind: 'Robotics',
    status: 'Archived',
    summary:
      'Robotik-Team am Athenaeum Stade und ein wichtiger Teil von Jacks technischer Arbeit seit 2020.',
    description:
      '2024 gewann das OnStage-Team die Hamburger RoboCup-Qualifikation, wurde Zweiter bei der Deutschen Meisterschaft in Kassel und qualifizierte sich für die Europameisterschaft.',
    year: 'seit 2020, Teamphase 2024',
    roles: ['Teamleiter im OnStage-Team'],
    technologies: ['Robotik', 'RoboCup OnStage'],
    tags: ['robotics', 'music', 'leadership'],
    featured: true,
  },
  {
    id: 'hackclub-stade',
    slug: 'hackclub-stade',
    name: 'Hackclub Stade',
    kind: 'Education',
    status: 'Paused',
    summary:
      'Lokaler, kostenloser Hack Club für selbstorganisierte technische Bildung am Athenaeum Stade.',
    description:
      'Der Club war Ausgangspunkt für mehrere spätere DJL-Projekte und pausiert seit dem 31. Januar 2025.',
    year: 'bis Januar 2025',
    roles: ['Gründer', 'Leitung'],
    technologies: [],
    tags: ['education', 'community', 'leadership'],
    featured: true,
  },
  {
    id: 'projektwoche',
    slug: 'projektwoche',
    name: 'Projektwoche: Nachhaltige Webentwicklung',
    kind: 'Education',
    status: 'Archived',
    summary:
      'Technische Infrastruktur und Webprojekte für eine schulische Projektwoche am Gymnasium Athenaeum Stade.',
    description:
      'Jack war Lead Developer. Hackclub Stade war an Organisation und Durchführung beteiligt.',
    year: '2025',
    roles: ['Lead Developer'],
    technologies: ['Webentwicklung', 'Setup-Tooling'],
    links: [
      {
        label: 'GitHub',
        href: 'https://github.com/DJL-Foundation/projektwoche',
      },
    ],
    tags: ['education', 'technology', 'leadership'],
  },
  {
    id: 'ai-ctx',
    slug: 'ai-ctx',
    name: 'ai-ctx',
    kind: 'Software',
    status: 'Archived',
    summary:
      'CLI, die relevante Dateien aus einer lokalen Codebase für Browser-KI in einen nutzbaren Kontext zusammenstellt.',
    description:
      'Entstand vor der breiten Verfügbarkeit agentischer Coding-Tools.',
    year: '2025',
    roles: ['Entwicklung'],
    technologies: ['TypeScript', 'Effect CLI'],
    links: [{ label: 'GitHub', href: 'https://github.com/JackatDJL/ai-ctx' }],
    tags: ['technology', 'developer-tools'],
  },
  {
    id: 'imkerportal-ruder',
    slug: 'imkerportal-ruder',
    name: 'Imkerportal Ruder',
    kind: 'Experiment',
    status: 'Paused',
    summary:
      'Idee für ein Portal zur Dokumentation von Eingriffen, Zustand und Historie eines Hobby-Imkereibetriebs.',
    description:
      'Zargen sollten über eindeutige Kennungen, etwa NFC, nachvollziehbar werden. Das Projekt kann später wieder aufgenommen werden.',
    year: 'seit 2025',
    roles: ['Konzept', 'Entwicklung'],
    technologies: ['NFC', 'Webentwicklung'],
    links: [
      {
        label: 'GitHub',
        href: 'https://github.com/JackatDJL/imkerportal-ruder',
      },
    ],
    tags: ['hardware', 'technology'],
  },
  {
    id: 'prism',
    slug: 'prism',
    name: 'Prism',
    kind: 'Education',
    status: 'Paused',
    summary:
      'Sammlung von Präsentationen, Handouts, Kahoot-Links und weiteren Materialien unter kurzen URLs.',
    description:
      'Begann als The Presentation Foundation, wurde später in Prism umbenannt. Ein Rebuild begann 2026, ist aber noch nicht weit fortgeschritten.',
    year: '2024 bis heute',
    roles: ['Konzept', 'Entwicklung'],
    technologies: ['Webentwicklung'],
    links: [
      { label: 'GitHub', href: 'https://github.com/DJL-Foundation/prism' },
    ],
    tags: ['education', 'technology'],
  },
  {
    id: 'songraten',
    slug: 'songraten',
    name: 'SongRaten',
    kind: 'Experiment',
    status: 'Archived',
    summary:
      'Musik-Ratespiel für die Schule mit HTML, CSS und clientseitigem JavaScript.',
    description:
      'Ein frühes Projekt zum Ausprobieren von CSS-Animationen, Browserlogik und GitHub Pages.',
    year: '2023',
    roles: ['Entwicklung'],
    technologies: ['HTML', 'CSS', 'JavaScript', 'GitHub Pages'],
    links: [
      { label: 'GitHub', href: 'https://github.com/JackatDJL/SongRaten' },
    ],
    tags: ['music', 'creative', 'technology'],
  },
  {
    id: 'djl-foundation',
    slug: 'djl-foundation',
    name: 'DJL Foundation',
    kind: 'Education',
    status: 'Maintained',
    summary:
      'Historischer Dachname für technische, schulische, gemeinnützige und experimentelle Projekte.',
    description:
      'Entstand aus Jacks Beschäftigung mit Hack Club und der Idee, technische Bildung und selbstorganisierte Jugendprojekte in Europa leichter möglich zu machen. DJL Foundation ist keine formale Stiftung und kein eingetragener Verein.',
    year: 'seit etwa 2023',
    roles: ['Initiator'],
    technologies: [],
    links: [{ label: 'Website', href: 'https://djl.foundation' }],
    tags: ['education', 'community', 'organisation'],
  },
  {
    id: 'n-plus',
    slug: 'n-plus',
    name: 'NoPlus',
    kind: 'Experiment',
    status: 'Archived',
    summary: 'Geplanter FPS als gemeinsames Experiment.',
    description:
      'Das Spiel wurde nicht fertig. Jack arbeitete an einem Scene- und Open-World-Loader, der Bereiche abhängig von der Spielerposition nachlädt.',
    year: '2023',
    roles: ['Entwicklung'],
    technologies: ['Game development'],
    links: [
      { label: 'GitHub', href: 'https://github.com/DJL-Foundation/NoPlus' },
    ],
    tags: ['experiment', 'technology'],
  },
  {
    id: 'foundation-drive',
    slug: 'foundation-drive',
    name: 'Foundation Drive',
    kind: 'Experiment',
    status: 'Archived',
    summary: 'Versuch, einen eigenen Google-Drive-ähnlichen Dienst zu bauen.',
    description:
      'Das Experiment zeigte früh, dass ein File Service dauerhaften Storage braucht. Ohne geeigneten Object Storage wurde es nicht weitergeführt.',
    year: '2025',
    roles: ['Entwicklung'],
    technologies: ['Webentwicklung', 'Object storage'],
    links: [
      {
        label: 'GitHub',
        href: 'https://github.com/DJL-Foundation/foundation-drive',
      },
    ],
    tags: ['experiment', 'infrastructure'],
  },
  {
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
    id: 'atheblues-onstage',
    title: 'Teamleiter, OnStage-Team',
    organisation: 'AtheBlues, Athenaeum Stade',
    start: '2024',
    description: 'Leitung im RoboCup-OnStage-Team.',
    highlights: [
      'Gewinn der Hamburger RoboCup-Qualifikation.',
      'Zweiter Platz bei der Deutschen Meisterschaft in Kassel.',
      'Qualifikation für die Europameisterschaft.',
    ],
    tags: ['robotics', 'music', 'leadership'],
  },
  {
    id: 'hackclub-stade-lead',
    title: 'Gründer und Leitung',
    organisation: 'Hackclub Stade',
    start: 'vor 2024',
    end: 'Januar 2025',
    description:
      'Aufbau eines lokalen Hack Clubs für kostenlose, selbstorganisierte technische Bildung.',
    highlights: ['Ausgangspunkt für mehrere spätere DJL-Projekte.'],
    tags: ['education', 'community', 'leadership'],
  },
  {
    id: 'erstwaehlerforum-lead',
    title: 'Initiator und Gründer',
    organisation: 'Erstwählerforum Stade',
    start: 'Dezember 2025',
    end: 'Juni 2026',
    description: 'Politisches Bildungsprojekt für junge Erstwähler in Stade.',
    highlights: ['Veranstaltung am 2. Juni 2026.'],
    tags: ['civic', 'education', 'leadership'],
  },
  {
    id: 'projektwoche-lead',
    title: 'Lead Developer',
    organisation: 'Projektwoche: Nachhaltige Webentwicklung',
    start: '2025',
    description:
      'Technische Infrastruktur und Webprojekte für eine schulische Projektwoche.',
    highlights: ['Organisation und Durchführung gemeinsam mit Hackclub Stade.'],
    tags: ['education', 'technology', 'leadership'],
  },
  {
    id: 'volt-europa-tech',
    title: 'Technische Mitarbeit',
    organisation: 'Volt Europa',
    start: '2026',
    description: 'Mitbetreuung mehrerer interner Repositories im Tech-Bereich.',
    highlights: [
      'Keine internen Projektnamen oder technischen Details veröffentlicht.',
    ],
    tags: ['technology', 'civic', 'internal'],
  },
]

export const education = [
  {
    id: 'athenaeum-stade',
    institution: 'Gymnasium Athenaeum Stade',
    programme: 'Schüler',
    start: 'Schulzeit',
    end: 'heute',
    note: 'Technische, musikalische und gesellschaftliche Projekte neben dem Schulalltag.',
  },
]

export const engagement = [
  {
    id: 'hackclub-stade-engagement',
    title: 'Technische Jugendbildung',
    organisation: 'Hackclub Stade',
    note: 'Lokales, kostenloses Angebot für selbstorganisierte technische Bildung.',
  },
  {
    id: 'erstwaehlerforum-engagement',
    title: 'Politische Bildung',
    organisation: 'Erstwählerforum Stade',
    note: 'Projekt für junge Erstwähler in Stade.',
  },
  {
    id: 'volt-europa-engagement',
    title: 'Technische Mitarbeit',
    organisation: 'Volt Europa',
    note: 'Aktive interne Arbeit im Tech-Bereich, ohne öffentliche interne Projektdetails.',
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
    id: 'music-practice',
    title: 'Klavier, Saxophon und Akkordeon',
    note: 'Musik ist ein eigener Teil von Jacks Arbeit und Alltag. Öffentliche Aufnahmen oder Auftritte können später ergänzt werden.',
  },
]

export const awards = [
  {
    id: 'robocup-2024-hamburg',
    title: 'Gewinn der Hamburger RoboCup-Qualifikation mit AtheBlues OnStage',
    year: '2024',
  },
  {
    id: 'robocup-2024-germany',
    title:
      'Zweiter Platz bei der Deutschen RoboCup-Meisterschaft mit AtheBlues OnStage',
    year: '2024',
  },
]

export const writing = [
  {
    slug: 'codeberg-und-github',
    title:
      'Why I moved some of my projects to Codeberg, and why GitHub still follows me around',
    date: '2026-09-04',
    summary:
      'Warum Codeberg für manche Projekte besser passt, GitHub aber weiterhin Teil meines Alltags bleibt.',
    tags: ['open source', 'Codeberg', 'GitHub'],
    body: [
      'Ich habe jahrelang fast alles auf GitHub gemacht. Repositories, Pull Requests, Actions, Deployments und viele Integrationen passen dort zusammen. Das ist bequem, und so zu tun, als wäre es das nicht, hilft niemandem.',
      'Trotzdem möchte ich für freie Projekte weniger selbstverständlich davon ausgehen, dass eine proprietäre Plattform das Zentrum sein muss. Codeberg passt für einige dieser Projekte besser. Die Plattform wird von einem gemeinnützigen Verein betrieben, basiert auf Forgejo und bleibt eine freie, nicht kommerzielle Alternative.',
      'Die praktische Seite ist weniger elegant. Viele Review-Tools, Preview-Deployments, Bots und andere Developer-SaaS-Angebote denken zuerst an GitHub. CI ist ebenfalls anders. Codeberg bietet Woodpecker CI. Forgejo Actions gibt es, aber gehostete Actions sind nicht überall gleich verfügbar.',
      'Mein aktuelles Setup ist deshalb pragmatisch. Einige freie Projekte liegen primär auf Codeberg. GitHub bleibt Mirror oder Integrationsendpunkt, wenn andere Werkzeuge es voraussetzen. Nicht jedes alte Archiv wird migriert. Codeberg statt GitHub ist für mich keine vollständige Antwort. Es heißt eher: Codeberg, wo es passt, GitHub, wo das Umfeld es noch verlangt.',
    ],
  },
]

export const publicProjects: Project[] = projects.map((project) =>
  project.status === 'Confidential'
    ? {
        id: project.id,
        slug: project.slug,
        name: 'Confidential technology project',
        kind: project.kind,
        status: project.status,
        summary:
          'Aktives vertrauliches Projekt. Öffentliche Details bleiben bewusst begrenzt.',
        year: project.year,
        roles: ['Project work'],
        technologies: [],
        tags: ['technology'],
      }
    : project,
)

export const homeProjects = publicProjects.filter((project) => project.featured)

export type CvProfileSlug =
  | 'general'
  | 'technology'
  | 'music'
  | 'management'
  | 'media'
  | 'entrepreneurship'

export type CvSection =
  | 'experience'
  | 'education'
  | 'projects'
  | 'skills'
  | 'music'
  | 'engagement'
  | 'awards'

export type CvProfile = {
  slug: CvProfileSlug
  title: string
  intro: string
  sectionOrder: CvSection[]
  featuredProjects: string[]
  featuredExperience: string[]
  featuredEngagement: string[]
  skillsFocus: string[]
  musicFocus: string[]
  optionalThemeAccent?: string
  optionalEmployer?: string
}

export type CvProfileAlias = {
  profile: CvProfileSlug
  overrides?: Partial<Omit<CvProfile, 'slug'>>
}

export const cvProfiles: Record<CvProfileSlug, CvProfile> = {
  general: {
    slug: 'general',
    title: 'Curriculum vitae',
    intro:
      'Schüler aus Stade, Entwickler, Musiker und Projektbauer mit Erfahrung in technischen, Bildungs- und zivilgesellschaftlichen Projekten.',
    sectionOrder: [
      'experience',
      'education',
      'projects',
      'skills',
      'music',
      'engagement',
      'awards',
    ],
    featuredProjects: [
      'prtop',
      'printgate',
      'atheblues',
      'erstwaehlerforum-stade',
    ],
    featuredExperience: [
      'atheblues-onstage',
      'hackclub-stade-lead',
      'erstwaehlerforum-lead',
      'projektwoche-lead',
    ],
    featuredEngagement: [
      'hackclub-stade-engagement',
      'erstwaehlerforum-engagement',
    ],
    skillsFocus: [],
    musicFocus: [],
  },
  technology: {
    slug: 'technology',
    title: 'Technology profile',
    intro:
      'A profile focused on software, systems thinking, and technical project work.',
    sectionOrder: [
      'experience',
      'projects',
      'skills',
      'education',
      'engagement',
      'music',
      'awards',
    ],
    featuredProjects: ['prtop', 'printgate', 'ai-ctx', 'atheblues'],
    featuredExperience: ['atheblues-onstage', 'projektwoche-lead'],
    featuredEngagement: [],
    skillsFocus: ['Build'],
    musicFocus: ['Music technology'],
    optionalThemeAccent: '#1f6092',
  },
  music: {
    slug: 'music',
    title: 'Music profile',
    intro:
      'A profile focused on music, instruments, technical curiosity, and project work.',
    sectionOrder: [
      'music',
      'experience',
      'projects',
      'skills',
      'education',
      'engagement',
      'awards',
    ],
    featuredProjects: ['atheblues', 'songraten', 'prism'],
    featuredExperience: ['atheblues-onstage'],
    featuredEngagement: [],
    skillsFocus: ['Music'],
    musicFocus: ['Performance', 'Music technology', 'Instruments'],
    optionalThemeAccent: '#8d5a3c',
  },
  management: {
    slug: 'management',
    title: 'Management profile',
    intro:
      'A profile focused on project leadership, collaboration, and delivery.',
    sectionOrder: [
      'experience',
      'projects',
      'engagement',
      'skills',
      'education',
      'music',
      'awards',
    ],
    featuredProjects: [
      'erstwaehlerforum-stade',
      'projektwoche',
      'hackclub-stade',
    ],
    featuredExperience: [
      'hackclub-stade-lead',
      'erstwaehlerforum-lead',
      'projektwoche-lead',
    ],
    featuredEngagement: [
      'hackclub-stade-engagement',
      'erstwaehlerforum-engagement',
    ],
    skillsFocus: ['Work with people'],
    musicFocus: [],
    optionalThemeAccent: '#335d52',
  },
  media: {
    slug: 'media',
    title: 'Media profile',
    intro:
      'A profile focused on communications, live production interest, technology, and public communication.',
    sectionOrder: [
      'experience',
      'projects',
      'engagement',
      'skills',
      'music',
      'education',
      'awards',
    ],
    featuredProjects: ['erstwaehlerforum-stade', 'atheblues', 'projektwoche'],
    featuredExperience: ['erstwaehlerforum-lead', 'atheblues-onstage'],
    featuredEngagement: ['erstwaehlerforum-engagement'],
    skillsFocus: ['Build', 'Work with people'],
    musicFocus: ['Music technology', 'Live production'],
    optionalThemeAccent: '#594b78',
  },
  entrepreneurship: {
    slug: 'entrepreneurship',
    title: 'Entrepreneurship profile',
    intro:
      'A profile focused on project development, business thinking, civic engagement, and leadership.',
    sectionOrder: [
      'projects',
      'experience',
      'engagement',
      'skills',
      'education',
      'music',
      'awards',
    ],
    featuredProjects: [
      'hackclub-stade',
      'erstwaehlerforum-stade',
      'projektwoche',
      'djl-foundation',
    ],
    featuredExperience: [
      'hackclub-stade-lead',
      'erstwaehlerforum-lead',
      'projektwoche-lead',
    ],
    featuredEngagement: [
      'hackclub-stade-engagement',
      'erstwaehlerforum-engagement',
      'volt-europa-engagement',
    ],
    skillsFocus: ['Work with people', 'Build'],
    musicFocus: [],
    optionalThemeAccent: '#825437',
  },
}

export const cvAliases = {
  airbus: {
    profile: 'technology',
    overrides: {
      title: 'Technology and systems profile',
      optionalEmployer: 'Airbus',
    },
  },
  steinway: {
    profile: 'music',
    overrides: {
      title: 'Music, craft, and technology profile',
      optionalEmployer: 'Steinway',
    },
  },
  yamaha: {
    profile: 'music',
    overrides: {
      title: 'Music, technology, and project profile',
      sectionOrder: [
        'music',
        'projects',
        'experience',
        'skills',
        'education',
        'engagement',
        'awards',
      ],
      skillsFocus: ['Music', 'Work with people'],
      optionalEmployer: 'Yamaha',
    },
  },
  ndr: {
    profile: 'media',
    overrides: {
      title: 'Media and technology profile',
      optionalEmployer: 'NDR',
    },
  },
  ihk: {
    profile: 'entrepreneurship',
    overrides: {
      title: 'Entrepreneurship and leadership profile',
      optionalEmployer: 'IHK',
    },
  },
} as const satisfies Record<string, CvProfileAlias>

export const publicCvSwitches = [
  { label: 'Technology', profile: 'technology' },
  { label: 'Music & media', profile: 'music' },
  { label: 'Leadership', profile: 'management' },
  { label: 'Entrepreneurship', profile: 'entrepreneurship' },
] as const satisfies ReadonlyArray<{ label: string; profile: CvProfileSlug }>

export function isCvProfileSlug(slug: string) {
  return slug in cvProfiles || slug in cvAliases
}

export function resolveCvProfile(slug?: string) {
  const alias =
    slug && slug in cvAliases
      ? cvAliases[slug as keyof typeof cvAliases]
      : undefined
  const profileId =
    alias?.profile ??
    (slug && slug in cvProfiles ? (slug as CvProfileSlug) : 'general')
  const profile = cvProfiles[profileId]
  return {
    ...profile,
    ...alias?.overrides,
    alias: alias ? slug : undefined,
  }
}

export function getCvProfileForRoute(slug: string) {
  return isCvProfileSlug(slug) ? resolveCvProfile(slug) : undefined
}

export function selectProjects(ids: string[]) {
  return publicProjects.filter(
    (project) => project.status !== 'Confidential' && ids.includes(project.id),
  )
}

export function selectExperience(ids: string[]) {
  return experience.filter((entry) => ids.includes(entry.id))
}

export function selectEngagement(ids: string[]) {
  return engagement.filter((entry) => ids.includes(entry.id))
}
