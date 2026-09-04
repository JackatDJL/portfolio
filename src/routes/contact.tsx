import { createFileRoute } from '@tanstack/react-router'
import { ExternalLink, PageIntro, SiteShell } from '../components/site'
import { site } from '../data/site'

export const Route = createFileRoute('/contact')({
  head: () => ({
    meta: [
      { title: 'Contact · Jack Ruder' },
      { name: 'description', content: 'Contact Jack Ruder.' },
    ],
  }),
  component: Contact,
})
function Contact() {
  return (
    <SiteShell>
      <PageIntro eyebrow="Kontakt" title="Schreib mir.">
        <p>
          Eine öffentliche E-Mail-Adresse ist noch nicht hinterlegt. Bis dahin
          ist GitHub der verlässliche öffentliche Kontaktweg.
        </p>
      </PageIntro>
      <section className="contact-list">
        {site.links.map((link) => (
          <ExternalLink key={link.label} href={link.href}>
            {link.label}
          </ExternalLink>
        ))}
      </section>
    </SiteShell>
  )
}
