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
      <PageIntro eyebrow="Contact" title="Start with a note.">
        <p>
          TODO: add a public contact address and a short note about the kinds of
          messages welcome here.
        </p>
      </PageIntro>
      <section className="contact-list">
        <a href="mailto:TODO@example.com">TODO: public email address</a>
        {site.links.map((link) => (
          <ExternalLink key={link.label} href={link.href}>
            {link.label}
          </ExternalLink>
        ))}
      </section>
    </SiteShell>
  )
}
