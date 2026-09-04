import { Link, createFileRoute } from '@tanstack/react-router'
import { ExternalLink, SiteShell, Status } from '../components/site'
import { engagement, publicProjects, site, writing } from '../data/site'

export const Route = createFileRoute('/')({
  head: () => ({
    meta: [
      { title: 'Jack Ruder' },
      { property: 'og:title', content: 'Jack Ruder' },
      { property: 'og:description', content: site.description },
    ],
  }),
  component: Home,
})

function Home() {
  return (
    <SiteShell>
      <section className="home-hero">
        <p className="eyebrow">jack.djl.foundation</p>
        <h1>
          Jack Ruder
          <br />
          <em>makes things work.</em>
        </h1>
        <p className="hero-copy">
          Student, developer, musician, and project builder. Interested in the
          practical work where technology, education, and civic life meet.
        </p>
        <Link className="text-link" to="/about">
          More about Jack <span>→</span>
        </Link>
      </section>
      <section className="home-grid">
        <div>
          <p className="eyebrow">Selected work</p>
          {publicProjects.slice(0, 2).map((project) => (
            <article className="project-tease" key={project.id}>
              <div>
                <Status>{project.status}</Status>
                <h2>{project.name}</h2>
                <p>{project.summary}</p>
              </div>
              <p className="project-meta">
                {project.kind} · {project.year}
              </p>
            </article>
          ))}
          <Link className="text-link" to="/projects">
            All projects <span>→</span>
          </Link>
        </div>
        <aside>
          <p className="eyebrow">Now</p>
          <p className="now-note">{site.now.note}</p>
          <ul>
            {site.now.items.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
          <Link className="text-link" to="/now">
            Full now page <span>→</span>
          </Link>
        </aside>
      </section>
      <section className="split-section">
        <div>
          <p className="eyebrow">Recent writing</p>
          <article>
            <p className="date">{writing[0].date}</p>
            <h2>{writing[0].title}</h2>
            <p>{writing[0].summary}</p>
            <Link className="text-link" to="/writing">
              Read writing <span>→</span>
            </Link>
          </article>
        </div>
        <div>
          <p className="eyebrow">Selected engagement</p>
          {engagement.map((item) => (
            <p key={item.id}>
              <strong>{item.title}</strong>
              <br />
              {item.organisation}
            </p>
          ))}
          <ExternalLink href={site.links[0].href}>
            {site.links[0].label}
          </ExternalLink>
        </div>
      </section>
    </SiteShell>
  )
}
