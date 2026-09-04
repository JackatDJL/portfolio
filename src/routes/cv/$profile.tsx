import { createFileRoute, notFound } from '@tanstack/react-router'
import { CvDocument } from '../../components/cv'
import { SiteShell } from '../../components/site'
import { getCvProfileForRoute, site } from '../../data/site'

export const Route = createFileRoute('/cv/$profile')({
  loader: ({ params }) => {
    const profile = getCvProfileForRoute(params.profile)
    if (!profile) throw notFound()
    return profile
  },
  head: ({ loaderData }) => ({
    meta: [
      { title: `${loaderData?.title ?? 'CV'} · Jack Ruder` },
      { name: 'description', content: loaderData?.intro ?? site.description },
      { property: 'og:title', content: loaderData?.title ?? 'Jack Ruder CV' },
      {
        property: 'og:description',
        content: loaderData?.intro ?? site.description,
      },
    ],
  }),
  component: CvProfile,
})
function CvProfile() {
  return (
    <SiteShell>
      <CvDocument profile={Route.useLoaderData()} />
    </SiteShell>
  )
}
