import { createFileRoute } from '@tanstack/react-router'
import { PageIntro, SiteShell } from '../components/site'
import { site } from '../data/site'

export const Route = createFileRoute('/now')({
  head: () => ({
    meta: [
      { title: 'Now · Jack Ruder' },
      { name: 'description', content: 'What Jack Ruder is working on now.' },
    ],
  }),
  component: Now,
})
function Now() {
  return (
    <SiteShell>
      <PageIntro eyebrow={`Updated ${site.now.updatedAt}`} title="Now.">
        <p>{site.now.note}</p>
      </PageIntro>
      <section className="now-list">
        {site.now.items.map((item, index) => (
          <p key={item}>
            <span>{String(index + 1).padStart(2, '0')}</span>
            {item}
          </p>
        ))}
      </section>
      <p className="prose-layout">
        A now page is a dated snapshot, not a biography. TODO: update this
        whenever the work changes.
      </p>
    </SiteShell>
  )
}
