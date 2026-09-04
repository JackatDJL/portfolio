import { Link } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { site } from '../data/site'

const nav = [
  ['About', '/about'],
  ['Projects', '/projects'],
  ['Writing', '/writing'],
  ['Now', '/now'],
  ['CV', '/cv'],
  ['Contact', '/contact'],
] as const

export function SiteShell({ children }: { children: ReactNode }) {
  return (
    <>
      <header className="site-header">
        <Link to="/" className="wordmark">
          JR<span>.</span>
        </Link>
        <nav aria-label="Main navigation">
          {nav.map(([label, to]) => (
            <Link key={to} to={to} activeProps={{ className: 'active' }}>
              {label}
            </Link>
          ))}
        </nav>
      </header>
      <main>{children}</main>
      <footer>
        <p>
          {site.name} · {site.profile.role}
        </p>
        {site.profile.email ? (
          <a href={`mailto:${site.profile.email}`}>Kontakt</a>
        ) : (
          <Link to="/contact">Kontakt</Link>
        )}
      </footer>
    </>
  )
}

export function PageIntro({
  eyebrow,
  title,
  children,
}: {
  eyebrow: string
  title: string
  children?: ReactNode
}) {
  return (
    <header className="page-intro">
      <p className="eyebrow">{eyebrow}</p>
      <h1>{title}</h1>
      {children && <div className="intro-copy">{children}</div>}
    </header>
  )
}

export function Status({ children }: { children: ReactNode }) {
  return <span className="status">{children}</span>
}

export function ExternalLink({
  href,
  children,
}: {
  href: string
  children: ReactNode
}) {
  return href.startsWith('TODO:') ? (
    <span className="todo-link">
      {children} <small>TODO</small>
    </span>
  ) : (
    <a href={href} target="_blank" rel="noreferrer">
      {children} ↗
    </a>
  )
}
