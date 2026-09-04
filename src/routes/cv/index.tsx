import { createFileRoute } from '@tanstack/react-router'
import { CvDocument, CvProfileLinks } from '../../components/cv'
import { PageIntro, SiteShell } from '../../components/site'
import { resolveCvProfile } from '../../data/site'

export const Route = createFileRoute('/cv/')({
  head: () => ({
    meta: [
      { title: 'CV · Jack Ruder' },
      { name: 'description', content: 'Jack Ruder curriculum vitae.' },
    ],
  }),
  component: Cv,
})
function Cv() {
  const profile = resolveCvProfile()
  return (
    <SiteShell>
      <PageIntro eyebrow="Curriculum vitae" title="The readable version.">
        <p>
          This web CV is designed for reading and printing. The downloadable PDF
          remains the canonical application document.
        </p>
      </PageIntro>
      <CvProfileLinks />
      <CvDocument profile={profile} />
    </SiteShell>
  )
}
