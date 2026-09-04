import { Link, createFileRoute, notFound } from '@tanstack/react-router'
import { PageIntro, SiteShell, Status } from '../components/site'
import { publicProjects, site } from '../data/site'

export const Route = createFileRoute('/projects/$slug')({
  loader: ({ params }) => {
    const project = publicProjects.find((entry) => entry.slug === params.slug)
    if (!project || project.status === 'Confidential') throw notFound()
    return project
  },
  head: ({ loaderData }) => ({
    meta: [
      { title: `${loaderData?.name ?? 'Project'} · Jack Ruder` },
      { name: 'description', content: loaderData?.summary ?? site.description },
      { property: 'og:title', content: loaderData?.name ?? 'Project' },
      {
        property: 'og:description',
        content: loaderData?.summary ?? site.description,
      },
    ],
  }),
  component: Project,
})

function Project() {
  const project = Route.useLoaderData()
  return (
    <SiteShell>
      <PageIntro
        eyebrow={`${project.kind} · ${project.year}`}
        title={project.name}
      >
        <Status>{project.status}</Status>
        <p>{project.summary}</p>
      </PageIntro>
      <article className="prose-layout">
        <p>{project.description}</p>
        <h2>Role and tools</h2>
        <p>{project.roles.join(' · ')}</p>
        <p>{project.technologies.join(' · ')}</p>
        {project.links?.map((link) => (
          <p key={link.href}>
            <a href={link.href}>{link.label} ↗</a>
          </p>
        ))}
        <Link className="text-link" to="/projects">
          Back to archive <span>←</span>
        </Link>
      </article>
    </SiteShell>
  )
}
