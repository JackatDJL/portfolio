import { Link } from '@tanstack/react-router'
import {
  awards,
  education,
  engagement,
  music,
  selectExperience,
  selectProjects,
  skills,
  site,
} from '../data/site'
import type { CvProfile, CvProfileId } from '../data/site'

export function CvDocument({
  profile,
}: {
  profile: CvProfile & { alias?: string }
}) {
  const projects = selectProjects(profile.featuredProjectTags)
  const experience = selectExperience(profile.featuredExperienceTags)
  return (
    <article className="cv-document">
      <header className="cv-header">
        <div>
          <p className="eyebrow">
            Jack Ruder · {profile.alias ? 'tailored CV' : 'canonical web CV'}
          </p>
          <h1>{profile.title}</h1>
          <p>{profile.intro}</p>
        </div>
        <div className="cv-actions">
          <a className="button" href="/cv/jack-ruder-cv.pdf" download>
            Download PDF <span aria-hidden>↓</span>
          </a>
          <p className="fine-print">
            PDF is the canonical application document.
            <br />
            Canonical URL: {site.url}/cv/
            {profile.id === 'general' ? '' : profile.id}
          </p>
        </div>
      </header>
      {profile.sections.map((section) => (
        <CvSection
          key={section}
          section={section}
          projects={projects}
          experience={experience}
        />
      ))}
    </article>
  )
}

function CvSection({
  section,
  projects,
  experience,
}: {
  section: string
  projects: ReturnType<typeof selectProjects>
  experience: ReturnType<typeof selectExperience>
}) {
  if (section === 'experience')
    return (
      <section className="cv-section">
        <h2>Experience</h2>
        {experience.map((entry) => (
          <div className="timeline-entry" key={entry.id}>
            <p className="date">
              {entry.start}
              {entry.end ? ` — ${entry.end}` : ''}
            </p>
            <div>
              <h3>{entry.title}</h3>
              <p className="subhead">{entry.organisation}</p>
              <p>{entry.description}</p>
              <ul>
                {entry.highlights.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>
          </div>
        ))}
      </section>
    )
  if (section === 'education')
    return (
      <section className="cv-section">
        <h2>Education</h2>
        {education.map((entry) => (
          <div className="timeline-entry" key={entry.id}>
            <p className="date">
              {entry.start} — {entry.end}
            </p>
            <div>
              <h3>{entry.programme}</h3>
              <p className="subhead">{entry.institution}</p>
              <p>{entry.note}</p>
            </div>
          </div>
        ))}
      </section>
    )
  if (section === 'projects')
    return (
      <section className="cv-section">
        <h2>Selected projects</h2>
        <div className="cv-projects">
          {projects.map((project) => (
            <details key={project.id}>
              <summary>
                <span>
                  <span className="project-name">{project.name}</span>
                  <small>
                    {project.kind} · {project.year}
                  </small>
                </span>
                <span aria-hidden>+</span>
              </summary>
              <div className="details-content">
                <p>{project.summary}</p>
                {project.description && <p>{project.description}</p>}
                <p className="tag-line">
                  {project.roles.join(' · ')}
                  {project.technologies.length > 0 &&
                    ` · ${project.technologies.join(', ')}`}
                </p>
                <Link to="/projects">View project archive</Link>
              </div>
            </details>
          ))}
        </div>
      </section>
    )
  if (section === 'skills')
    return (
      <section className="cv-section">
        <h2>Ways of working</h2>
        <div className="skill-groups">
          {skills.map((group) => (
            <div key={group.category}>
              <h3>{group.category}</h3>
              <p>{group.items.join(' · ')}</p>
            </div>
          ))}
        </div>
      </section>
    )
  if (section === 'music')
    return (
      <section className="cv-section">
        <h2>Music</h2>
        {music.map((entry) => (
          <p key={entry.id}>
            <strong>{entry.title}</strong>
            <br />
            {entry.note}
          </p>
        ))}
      </section>
    )
  if (section === 'engagement')
    return (
      <section className="cv-section">
        <h2>Engagement</h2>
        {engagement.map((entry) => (
          <p key={entry.id}>
            <strong>{entry.title}</strong>
            <br />
            {entry.organisation} · {entry.note}
          </p>
        ))}
      </section>
    )
  if (section === 'awards')
    return (
      <section className="cv-section">
        <h2>Awards</h2>
        {awards.map((entry) => (
          <p key={entry.id}>
            <strong>{entry.title}</strong> · {entry.year}
          </p>
        ))}
      </section>
    )
  return null
}

export function CvProfileLinks() {
  const profiles: CvProfileId[] = [
    'technology',
    'music',
    'management',
    'media',
    'entrepreneurship',
  ]
  return (
    <nav className="cv-profile-links" aria-label="CV profiles">
      {profiles.map((profile) => (
        <Link key={profile} to="/cv/$profile" params={{ profile }}>
          {profile}
        </Link>
      ))}
    </nav>
  )
}
