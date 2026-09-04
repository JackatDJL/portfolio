import { Link, createFileRoute, notFound } from '@tanstack/react-router'
import { PageIntro, SiteShell } from '../components/site'
import { writing } from '../data/site'

export const Route = createFileRoute('/writing/$slug')({
  loader: ({ params }) => {
    const post = writing.find((entry) => entry.slug === params.slug)
    if (!post) throw notFound()
    return post
  },
  head: ({ loaderData }) => ({
    meta: [
      { title: `${loaderData?.title ?? 'Writing'} · Jack Ruder` },
      { name: 'description', content: loaderData?.summary ?? '' },
      { property: 'og:type', content: 'article' },
    ],
  }),
  component: Post,
})

function Post() {
  const post = Route.useLoaderData()
  return (
    <SiteShell>
      <PageIntro eyebrow={post.date} title={post.title}>
        <p>{post.summary}</p>
      </PageIntro>
      <article className="prose-layout">
        <p>{post.body}</p>
        <Link className="text-link" to="/writing">
          All writing <span>←</span>
        </Link>
      </article>
    </SiteShell>
  )
}
