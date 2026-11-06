# Private application PDF workflow

This extends application management from PR #32. It reuses `CvProfileContentResolver`, `CvViewModel`, the CV LaTeX template and `CvPdfRenderer`. `CvPdfRenderer::compile` renders both letters and CVs. There is no second CV renderer or browser-to-PDF service.

## Control Panel

Save edits before using the PDF actions:

- **Vollständige PDF prüfen** creates a fresh private inline preview. It bypasses the canonical cache and deletes its result after sending.
- **PDF exportieren** downloads the canonical composite. Every request resolves current dependencies before checking the content fingerprint.
- **Empfängerlink erstellen / ersetzen** creates an optional permanent recipient capability and replaces any earlier one. Copy it immediately; the raw token is shown once and never stored. It grants access to the complete private PDF, including future saved changes. Preview the saved application before sharing it.
- **Empfängerlink widerrufen** invalidates the link and existing recipient sessions. Already downloaded copies cannot be recalled.

Only super users may use CP generation and link-management actions. The existing CV-only download remains available. No action sends email.

A full export requires recipient organisation/address, subject, salutation, non-empty body, closing and configured sender name/address. Drafts can still be saved without these export requirements. Sender identity comes from the site globals and private contacts from the encrypted CV globals. A malformed configured private field stops the export; it never becomes a public placeholder. Unconfigured optional contacts are omitted. CV profiles/templates are resolved on a detached overlay and never saved.

## German letter and composition

The letter template uses A4, a 25 mm left margin, 20 mm right margin, a return-address/recipient zone starting at 46 mm, date, bold subject, salutation, flowing body, closing, optional typed name and linked attachment list. Fira Sans matches the existing CV. The accent is restrained and adjusted for contrast. German Babel supplies hyphenation. This is oriented towards DIN 5008 form B, not a certification of compliance.

The letter uses two LuaLaTeX passes to position its overlay. Longer letters continue on A4 with normal margins. Closing/signature stay together. No raw signature image is copied from globals. Bard supports paragraphs, bold/italic, hard breaks, lists and HTTP(S), mailto and tel links. `#cv` and `#document-1`, `#document-2`, etc. target the corresponding composite parts. User text is escaped; arbitrary TeX, unsupported blocks and unsafe URLs fail validation.

Order is always letter, freshly resolved private CV, then effective supporting documents in author-defined order. Inherit mode uses global defaults; an explicit empty override stays empty. Files must resolve inside the private application-document disk. Missing, encrypted, malformed or oversized documents stop the complete export. Defaults limit attachments to 20 MiB each, 100 MiB total and 300 composite pages.

The pinned `pypdf` helper composes existing PDFs without rasterizing pages. It imports annotations/outlines, groups form fields per input and adds a bookmark per part. Internal named destinations are remapped to output page references, including identical names in different attachments. Letter attachment links target the correct part starts. External URI actions remain intact; accidental surrounding whitespace is removed. The shared URL escaping encodes additional fragment hashes so hyperref does not truncate Matrix room URLs. Supporting documents keep their original page sizes/rotation; generated letter/CV pages are A4. See the [pypdf merging documentation](https://pypdf.readthedocs.io/en/stable/user/merging-pdfs.html).

Remote-file actions still require the external file. Existing cryptographic signatures cannot remain valid after composition. Unresolved internal destinations cause a failure rather than a silently dropped link. No QES is applied.

## Recipient access

`/applications/{UUID}` is a generic access page without sender, recipient or application details. There is no collection index. The page removes `#application=TOKEN` before posting it to `/applications/token-exchange`. The exchange uses normal Laravel CSRF protections, checks origin and is rate-limited. Query-string tokens are rejected. The clean `/applications/{UUID}/pdf` route requires the scoped session.

The database stores only SHA-256 hashes of random 64-character secrets. Sessions store only hashes under application-specific scopes. Each PDF request checks the current hash, expiry/revocation and application existence, including a second authorization check after generation. Rotation and revocation invalidate existing sessions. CV grants never authorize applications, and application grants never create CV grants. Authorized application rendering explicitly includes private CV data independently of the viewer's CV session. There is no public-data downgrade flag.

Responses have private/no-store, no-referrer and noindex headers, with same-origin framing. Statamic static caching excludes `/applications*`; the Content API remains disabled. Range requests still pass authorization.

## Cache and failure behavior

Canonical PDFs live under `storage/app/private/application-pdf-cache`, with 0600 files and 0700 directories. URLs contain no filesystem/cache paths. The fingerprint includes application groups, current resolved CV/profile/template/public entries and private contacts, rendered letter/reusable texts, immutable photo and ordered document byte hashes, code/template/font fingerprints, LuaLaTeX/composition versions and the post-processor version. Filenames use an HMAC keyed by `APP_KEY`. A checksum detects damaged cached PDFs.

A bounded per-application lock serializes publication. Complete, validated output is atomically renamed into place. Keep the work/cache paths on the same filesystem. Private job directories hold attachment/photo snapshots matching the fingerprint. Failures return a redacted 503 with the stage; missing export data returns an actionable 422 in the CP and a generic unavailable response for recipients. No compiler/parser logs, private document text, partial PDF or stale fallback is sent. Intermediate sources/manifests/PDFs are cleaned in `finally` blocks. Composition has page/input/memory limits. The pipeline shares a 90-second generation/lock budget across letter/CV compilation and composition, below the existing web timeout.

Old versions are pruned after 30 days when a new version is generated. Administrators should also remove abandoned private work directories after a crashed process and include caches in their secure retention policy. Never expose or copy cache/work directories into public storage or SSG output. Cache reuse never extends recipient authorization.

`ApplicationPdfPostProcessor` runs after composition and before final validation/cache publication. The default does nothing. Future implementations must version behavior/configuration changes, operate only on the private file and throw on failure. After the hook the pipeline only validates/checksums and moves the file. This is an extension point for later work, not QES support.

## Runtime and validation

Docker installs the Python environment and German TeX support. Existing installations need the matching dependencies, `APPLICATION_PDF_PYTHON` set to that Python executable, and `php artisan migrate` for `application_access_tokens`. CP actions never migrate or deploy.

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
