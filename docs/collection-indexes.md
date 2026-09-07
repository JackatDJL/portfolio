# Collection indexes

Three independent Antlers templates render Statamic collections into HTML. The only shared partials are the count/title intro and non-linked taxonomy chips. `collections.css` holds the collection compositions; `collection-preview.js` is dynamically imported only on Projects. No content or blueprint changes.

## Compositions and fields

- `/projekte`: one featured entry shortcut, then every project ordered by `started_at` descending. A sticky desktop stage follows hover, keyboard focus, or the scroll observation region. Mobile and no-JS views retain inline images. Consumes `title`, `url`, `summary`, `project_status` and its label, `featured`, `started_at`, `ended_at`, `featured_technology` labels, `topics` titles, and `cover` with asset alt/width/height. Missing covers use the project's own title/status/technology. Secondary repositories and full technology lists remain on detail pages.
- `/blog`: featured-first/latest lead, then remaining entries ordered by date, excluding the lead ID. The current single published article appears once. Consumes `id`, `url`, `title`, `summary`, `featured`, `date`, `topics`. Dates include their year; no empty archive heading is rendered. Covers are intentionally unnecessary.
- `/publikationen`: chronological records with a dedicated date column, bibliographic body, and document access column. Native details exposes the abstract. Consumes `url`, `title`, `date`, `publication_type` label, `peer_reviewed`, `authors.name`, `publisher`, `doi`, `document_url`, `abstract`, `topics`, `cover` width/height. Optional fields are conditional. No PDF.js or PDF requests on the index.

All collection queries use Statamic's published-entry behavior. Draft demo records remain excluded. Counts, URLs, field labels, and topics come from Statamic. Interface labels are static German copy in the templates, not purported CMS descriptions.

## Interaction and access

The project stage is an aria-hidden duplicate with no controls. Real record links retain keyboard focus and primary detail access. The observer never overrides an interacting keyboard user. A 180ms Web Animations transition changes only the stage, and cancels on subsequent activation. Reduced-motion changes are observed live. CSS arrow/underline interactions and native publication disclosures need no GSAP plugin. All content remains visible if JavaScript fails; no scroll interception is used.

At 64rem Projects gains the sticky stage. Below that breakpoint its images stay with their records. At 48rem Blog gains a date gutter and Publications gains its bibliography/access columns. Smaller screens stack these elements. Images use Glide, responsive sources where useful, lazy loading, intrinsic dimensions, and async decoding. Existing semantic theme, type, spacing, focus, chip, button, and border tokens are reused. No new design tokens.

## Verification, 2026-09-06

- Production application stages passed: frozen Bun install, Vite build, publication PDF mirror, Stache warm, SSG generation. All three index URLs explicitly participate in SSG and were generated among 11 HTML files. The host-provisioning part of `build.sh` is Vercel-specific and was not executed locally. No deployment was performed.
- `php artisan test --compact`: 5 passed, 16 assertions.
- Existing `bun run test:browser`: 1 passed.
- Pint on changed PHP files and `git diff --check`: passed.
- Chromium tested the generated static HTML at 1920, 1280, 768, and 390px, light/dark: 24 route/viewport/theme combinations, no page/console errors, no horizontal overflow, no demo entries. Desktop hover, focus, and native scroll updated the preview. The scroll regression checks the last record when two rows overlap the observer band. Visible images were scrolled into view and checked for successful decoding. Native abstracts opened with Enter. Reduced-motion and no-JS passes covered every route.
- Visual inspection confirmed the long Codeberg title wraps, publication identifiers stay within columns, project technology/topic lists wrap, inline covers load on mobile, and endings leave space before the footer. Project focus has a visible outline and selects the corresponding artifact.
- `scripts/check-collection-indexes.mjs` repeats the matrix against a generated site served on port 8017. Override `COLLECTION_BASE_URL` for another origin. Screenshots go to `/tmp/collection-*.png`.

Publication and Blog coverless rendering is verified with real records. The project coverless branch is source-reviewed; every current project has a cover. There are no synthetic CMS entries. Additional Blog archive rows are not visually represented by today's one-post collection.

Topics routes, Homepage, filtering/search, PDF thumbnail generation, and global transitions remain deferred. Detail pages, navigation, footer, Bard, PDF reader, citation UI, and theme tokens are unchanged.
