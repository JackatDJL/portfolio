import { createFileRoute } from '@tanstack/react-router'
import { PageIntro, SiteShell } from '../components/site'
import { site } from '../data/site'

export const Route = createFileRoute('/about')({
  head: () => ({
    meta: [
      { title: 'About · Jack Ruder' },
      { name: 'description', content: 'About Jack Ruder.' },
    ],
  }),
  component: About,
})

function About() {
  return (
    <SiteShell>
      <PageIntro eyebrow="About" title="A person behind the projects.">
        <p>{site.profile.bio}</p>
      </PageIntro>
      <section className="prose-layout">
        <p>
          This site is a working archive, not a sales brochure. It collects
          public project work, writing, music, and civic interests as they
          develop.
        </p>
        <p>
          TODO: add a biographical narrative, the places and communities that
          matter, and the thread that connects Jack's work.
        </p>
      </section>
    </SiteShell>
  )
}
