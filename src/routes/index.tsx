import { Link, createFileRoute } from '@tanstack/react-router'
import { SiteShell, Status } from '../components/site'
import { publicProjects, site, writing } from '../data/site'

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
      <section className="home-introduction">
        <p className="home-greeting">{site.home.greeting}</p>
        <p className="home-intro">{site.home.introduction}</p>
        <p className="home-stamp">
          Zuletzt aktualisiert: {site.now.updatedAt} ·{' '}
          <Link to="/about">Über mich</Link>
        </p>
      </section>
      <section className="home-desk">
        <div className="desk-main">
          <header className="desk-heading">
            <h1>Arbeitsnotizen</h1>
            <p>{site.home.archiveNote}</p>
          </header>
          <div className="project-notes">
            {publicProjects.map((project) => (
              <article className="project-note" key={project.id}>
                <div className="project-note-heading">
                  <p>{project.year}</p>
                  <Status>{project.status}</Status>
                </div>
                {project.image && (
                  <img src={project.image.src} alt={project.image.alt} />
                )}
                <h2>{project.name}</h2>
                <p>{project.summary}</p>
                <p className="project-record">
                  {project.kind}
                  {project.technologies.length > 0 &&
                    ` · ${project.technologies.join(', ')}`}
                </p>
                {project.status !== 'Confidential' && (
                  <Link to="/projects/$slug" params={{ slug: project.slug }}>
                    Zum Projekteintrag
                  </Link>
                )}
              </article>
            ))}
          </div>
          <Link className="plain-link" to="/projects">
            Alle Projekte im Archiv →
          </Link>
        </div>
        <aside className="desk-index">
          <div>
            <h2>Woran ich gerade arbeite</h2>
            <p>{site.now.note}</p>
            <ul>
              {site.now.items.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
            <Link to="/now">Mehr dazu</Link>
          </div>
          <div>
            <h2>Bereiche</h2>
            <ul className="plain-list">
              <li>Software und offene Technik</li>
              <li>Musik und Instrumente</li>
              <li>Bildung und Vermittlung</li>
              <li>Zivilgesellschaft und Politik</li>
            </ul>
          </div>
          <div>
            <h2>Notizen</h2>
            {writing.slice(0, 1).map((post) => (
              <p key={post.slug}>
                <time dateTime={post.date}>{post.date}</time>
                <br />
                <Link to="/writing/$slug" params={{ slug: post.slug }}>
                  {post.title}
                </Link>
              </p>
            ))}
            <Link to="/writing">Alle Notizen</Link>
          </div>
        </aside>
      </section>
    </SiteShell>
  )
}
