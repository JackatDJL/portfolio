import { Link, createFileRoute } from '@tanstack/react-router'
import { PageIntro, SiteShell, Status } from '../components/site'
import { publicProjects } from '../data/site'

export const Route = createFileRoute('/projects')({
  head: () => ({
    meta: [
      { title: 'Projects · Jack Ruder' },
      {
        name: 'description',
        content: 'A public archive of Jack Ruder projects.',
      },
    ],
  }),
  component: Projects,
})

function Projects() {
  return (
    <SiteShell>
      <PageIntro eyebrow="Project archive" title="Work with a trail behind it.">
        <p>
          Software, robotics, education, civic work, events, and experiments.
          Some projects remain intentionally redacted.
        </p>
      </PageIntro>
      <div className="archive-controls" aria-label="Project categories">
        <span>All work</span>
        <span>Public archive</span>
      </div>
      <section className="project-list">
        {publicProjects.map((project) => (
          <article key={project.id} className="project-row">
            <div>
              <Status>{project.status}</Status>
              <h2>{project.name}</h2>
              <p>{project.summary}</p>
              {project.status !== 'Confidential' && (
                <Link to="/projects/$slug" params={{ slug: project.slug }}>
                  Project note →
                </Link>
              )}
            </div>
            <div className="project-meta">
              <p>{project.kind}</p>
              <p>{project.year}</p>
            </div>
          </article>
        ))}
      </section>
    </SiteShell>
  )
}
