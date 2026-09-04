import { Link, createFileRoute } from '@tanstack/react-router'
import { PageIntro, SiteShell } from '../components/site'
import { writing } from '../data/site'

export const Route = createFileRoute('/writing')({
  head: () => ({
    meta: [
      { title: 'Writing · Jack Ruder' },
      { name: 'description', content: 'Writing by Jack Ruder.' },
    ],
  }),
  component: Writing,
})

function Writing() {
  return (
    <SiteShell>
      <PageIntro eyebrow="Writing" title="Notes, essays, and field reports.">
        <p>
          Writing is kept close to the work. The index will grow as pieces are
          published.
        </p>
      </PageIntro>
      <section className="writing-list">
        {writing.map((post) => (
          <article key={post.slug}>
            <p className="date">{post.date}</p>
            <h2>
              <Link to="/writing/$slug" params={{ slug: post.slug }}>
                {post.title}
              </Link>
            </h2>
            <p>{post.summary}</p>
            <p className="tag-line">{post.tags.join(' · ')}</p>
          </article>
        ))}
      </section>
    </SiteShell>
  )
}
