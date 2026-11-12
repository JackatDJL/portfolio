# Private application PDF workflow

This extends application management from PR #32. It reuses `CvProfileContentResolver`, `CvViewModel`, the CV LaTeX template and `CvPdfRenderer`. `CvPdfRenderer::compile` renders both letters and CVs. There is no second CV renderer or browser-to-PDF service.

## Control Panel

Save edits before using the PDF actions:

- **Vollständige PDF prüfen** creates a fresh private inline preview. It bypasses the canonical cache and deletes its result after sending.
- **PDF exportieren** downloads the canonical composite. Every request resolves current dependencies before checking the content fingerprint.
- **Empfängerzugriff erstellen** issues a named independent grant. Copy the URL immediately; its raw token is shown once and never stored. It grants access to the complete private PDF, including future saved changes. Preview the saved application before sharing it.
- The grant list shows labels and timestamps without secrets. Revoke one grant or explicitly revoke all grants. Existing recipient sessions are checked against the current grant state on every request. Already downloaded copies cannot be recalled.

Only super users may use CP generation and link-management actions. The existing CV-only download remains available. No action sends email.

A full export requires recipient organisation/address, subject, salutation, non-empty body, closing and configured sender name/address. Drafts can still be saved without these export requirements. Sender identity comes from the site globals and private contacts from the encrypted CV globals. A malformed configured private field stops the export; it never becomes a public placeholder. Unconfigured optional contacts are omitted, except that `ApplicationLetter::make()` uses the CV's public email when its private email is empty. Review the preview to confirm which address will appear before sharing. CV profiles/templates are resolved on a detached overlay and never saved.

## German letter and composition

The Anschreiben follows the supplied reference's first-page layout: A4 with 25 mm margins, Noto Sans SemiCondensed at 11 pt, a plain right-aligned 10 pt sender block, a recipient block near 57 mm, and a right-aligned German date line with the sender's city. A bold subject supports authored line breaks, followed by the salutation, compact paragraphs, closing and optional typed name or handwritten graphic. Text is black and email/URL links are blue. There is no decorative rule, repeated return-address line, page number or automatically appended Anlagen block.

The letter fonts are bundled under `resources/application-fonts` with their OFL license and source attribution. They are separate from the existing CV fonts, and their bytes participate in the application export fingerprint. The export reuses the existing CV template, renderer and resolved profile. When enabled, the private handwritten signature adds a dated signature strip to that application CV without changing stored profile content.

The letter uses two LuaLaTeX passes to position its overlay. Longer letters continue on A4 with normal margins, keeping closing/signature together. A selected private handwritten signature is materialized only in the private export work directory, then reused in the letter and after the final content of the application CV. The CV signature strip uses the saved application date or manual override. No signature image or personal content is imported from the reference. The same existing CV renderer and profile resolver render the application CV. Bard supports paragraphs, bold/italic, hard breaks, lists and HTTP(S), mailto and tel links. Authors can use `#cv` and `#document-1`, `#document-2`, etc. in their own letter text; composite part bookmarks also remain available. User text is escaped, and unsupported blocks and unsafe URLs fail validation.

Order is always letter, freshly resolved private CV, then effective supporting documents in author-defined order. Inherit mode uses global defaults; an explicit empty override stays empty. Files must resolve inside the private application-document disk. Missing, encrypted, malformed or oversized documents stop the complete export. Defaults limit attachments to 20 MiB each, 100 MiB total and 300 composite pages.

The pinned `pypdf` helper composes existing PDFs without rasterizing pages. It imports annotations/outlines, groups form fields per input and adds a bookmark per part. Internal named destinations are remapped to output page references, including identical names in different attachments. Letter attachment links target the correct part starts. External URI actions remain intact; accidental surrounding whitespace is removed. The shared URL escaping encodes additional fragment hashes so hyperref does not truncate Matrix room URLs. Supporting documents keep their original page sizes/rotation; generated letter/CV pages are A4. See the [pypdf merging documentation](https://pypdf.readthedocs.io/en/stable/user/merging-pdfs.html).

Remote-file actions still require the external file. Existing cryptographic signatures cannot remain valid after composition. Unresolved internal destinations cause a failure rather than a silently dropped link. No QES is applied.

## Recipient access

`/applications/{UUID}` is a generic access page without sender, recipient or application details. There is no collection index. The page removes `#application=TOKEN` before posting it to `/applications/token-exchange`. The exchange uses normal Laravel CSRF protections, checks origin and is rate-limited. Query-string tokens are rejected. The clean `/applications/{UUID}/pdf` route requires the scoped session.

The database stores only SHA-256 hashes of random 64-character secrets; grant labels are encrypted. Each grant has its own UUID and creation, expiry, last-access and revocation timestamps. New grants never replace existing ones. The CP can revoke one grant or all grants, and displays no token hashes or bearer secrets. The new table migration copies existing token hashes and state without changing old recipient URLs. Sessions store only a scoped grant UUID (or an existing hash during compatibility transition), and each authorized request rechecks the grant's current expiry and revocation state, including a second authorization check after PDF generation. CV grants never authorize applications, and application grants never create CV grants. Authorized application rendering explicitly includes private CV data independently of the viewer's CV session. There is no public-data downgrade flag.

Responses have private/no-store, no-referrer and noindex headers, with same-origin framing. Statamic static caching excludes `/applications*`; the Content API remains disabled. Range requests still pass authorization.

## Cache and failure behavior

Canonical PDFs live under `storage/app/private/application-pdf-cache`, with 0600 files and 0700 directories. URLs contain no filesystem/cache paths. The fingerprint includes application groups, current resolved CV/profile/template/public entries and private contacts, rendered letter/reusable texts, immutable photo and ordered document byte hashes, code/template/font fingerprints, LuaLaTeX/composition versions and the post-processor version. Filenames use an HMAC keyed by `APP_KEY`. A checksum detects damaged cached PDFs.

A bounded per-application lock serializes publication. Complete, validated output is atomically renamed into place. Keep the work/cache paths on the same filesystem. Private job directories hold attachment/photo snapshots matching the fingerprint. Failures return a redacted 503 with the stage and a random error reference. For LuaLaTeX failures, the matching application log record also includes an allowlisted diagnostic category, numeric source line (when available) and 1-based compiler pass. Categories include `undefined_control_sequence`, `missing_file`, `font_error`, `image_error`, `runaway_argument`, `latex_error`, `tex_error` and `compiler_error`. These values help distinguish template/content errors from missing runtime resources without recording TeX source, private log lines, paths, compiler output or exception messages. Use the reference to locate the corresponding application log record; share only the reference, stage and three compiler diagnostic fields when asking for help. No compiler/parser logs, private document text, partial PDF or stale fallback is sent or logged. Missing export data returns an actionable 422 in the CP and a generic unavailable response for recipients. Intermediate sources/manifests/PDFs are cleaned in `finally` blocks. Composition has page/input/memory limits. The pipeline shares a 90-second generation/lock budget across letter/CV compilation and composition, below the existing web timeout.

Old versions are pruned after 30 days when a new version is generated. Administrators should also remove abandoned private work directories after a crashed process and include caches in their secure retention policy. Never expose or copy cache/work directories into public storage or SSG output. Cache reuse never extends recipient authorization.

`ApplicationPdfPostProcessor` runs after composition and before final validation/cache publication. The default does nothing. Future implementations must version behavior/configuration changes, operate only on the private file and throw on failure. After the hook the pipeline only validates/checksums and moves the file. This is an extension point for later work, not QES support.

## Runtime and validation

Docker installs the Python environment and German TeX support. Existing installations need the matching dependencies, `APPLICATION_PDF_PYTHON` set to that Python executable, and `php artisan migrate` for `application_access_tokens` and `application_recipient_grants`. The latter migration preserves existing token hashes and grant state. CP actions never migrate or deploy.

```sh
uv venv /tmp/portfolio-pdf-venv
uv pip install --python /tmp/portfolio-pdf-venv/bin/python -r requirements-pdf.txt
APPLICATION_PDF_PYTHON=/tmp/portfolio-pdf-venv/bin/python STATAMIC_PRO_ENABLED=true php artisan test --compact
```

Keep the existing LuaLaTeX packages/fonts and add German Babel/hyphenation, such as `texlive-lang-german`. Poppler is needed for tests/evidence, not production composition.

Integration tests use synthetic private data and real LuaLaTeX/pypdf output. They cover address coordinates/page bounds, A4 pagination, part order, duplicate named destinations/external URLs, private contacts, permissions/CSRF/scope/expiry/revocation, cache invalidation/integrity/concurrency and redacted failures/post-processing.

Generate evidence in a dedicated directory outside the checkout:

```sh
pdf_evidence_dir=$(mktemp -d /tmp/portfolio-pdf-evidence.XXXXXX)
APPLICATION_PDF_PYTHON=/tmp/portfolio-pdf-venv/bin/python php scripts/render-application-pdf-evidence.php "$pdf_evidence_dir"
```

The PDF and rendered pages use explicitly synthetic contact/recipient data. The CV otherwise uses existing public content. GitHub Actions runs the application tests and uploads rendered-page/PDF artifacts for 14 days. No PR-only evidence belongs in Git.

Electronic signing is a separate explicit operation on the final composite. See [private signature workflow and validation limits](application-signatures.md) and [official provider research](application-signature-provider-research.md). Rendering never initiates signing.
