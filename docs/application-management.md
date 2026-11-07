# Private application management

Applications are managed in Statamic under **Content → Bewerbungen**. There is no public application index or email action. Optional scoped recipient PDF links are documented in [the PDF workflow](application-pdf-workflow.md). CV grants do not grant application access. Only Statamic super users can manage the collection, its defaults and its document container. This restriction runs before Statamic's broad `configure collections` and asset permissions.

## Editing an application

1. Create an entry and choose a neutral internal reference, such as `2027-001`. The reference and workflow status are unencrypted metadata. Put organisation, position, recipient and contact information in the protected Empfänger tab.
2. Write the cover letter in Bard. Optional reusable blocks are managed under **Globals → Bewerbungsvorgaben → Textbausteine**. Give each a unique, stable key. A Bard block references that key; changes to a reusable block affect applications using it. Missing components fail resolution rather than silently producing an incomplete letter.
3. Select an existing CV profile, or leave the selection empty for the default CV. Each content section supports inherit, override and hide using the existing CV selectors. An introduction or accent applies only to this application. Shared profiles, templates and globals are never saved by the resolver. Existing rules still exclude unpublished and confidential project references.
4. Upload PDFs into **Private Bewerbungsdokumente**. Uploads accept only PDF MIME/extension combinations, up to 20 MiB each. Use neutral filenames. Order the attachment rows by dragging them. The global defaults supply the ordered list when the application uses `Globale Vorgaben`. `Eigene Auswahl` replaces the whole list, including an intentionally empty list. Review inherited documents before using an application.
5. The signature configuration supports no signature or a typed name. No raw signature image is accepted or copied from the CV globals.
6. Record correspondence, private notes, follow-up state and date in Verlauf. The application date is initialized on first save. The manual override takes precedence. Save edits before using `Datum auf heute setzen`; the action resets the saved date and clears its override, then reloads the editor.

The workflow status is separate from Statamic publication. Application saves force `published: false`, disable revisions and use an opaque slug. The collection has no route or preview target. No sample biographies, credentials or applications are seeded.

## Storage and access

`ApplicationPrivateGrid` extends Statamic's native Grid fieldtype. Its nested fields use native Bard, Entries, Assets, Date and other existing CP controls. It encrypts the processed JSON using Laravel's authenticated encryption and decrypts only for CP preprocessing or an explicit application resolver. Normal augmentation, shallow augmentation and query indexing return no private value. Invalid ciphertext fails closed. Save listeners also encrypt arrays supplied through PHP and reject unknown unprotected top-level entry fields.

The application entries live in the existing non-public content tree, with encrypted field groups. Application entries, localized defaults and asset metadata are ignored by Git. Only collection, blueprint and container definitions are tracked. The organisation may be shown in the authenticated CP listing. The title, status, IDs, edit timestamps and user IDs are metadata, so do not put confidential information in the title. Back up both runtime content and the private storage directory securely, together with a separately protected `APP_KEY`; losing the key loses the encrypted content. Do not rotate the key without migrating existing ciphertext.

The `application_documents` disk is rooted at `storage/app/private/application-documents`, has private permissions, no URL and no Laravel file serving. Statamic's authenticated asset preview/download routes enforce the same super-user restriction. Application and document responses receive `private, no-store`, `no-referrer`, `noindex` and `nosniff` headers. PDFs remain documents supplied by the administrator; the system does not sanitize PDF internals or claim to virus-scan them. It never automatically sends or opens an uploaded attachment.

Keep runtime content and private storage persistent when deploying. Git-based content sync must preserve ignored application records, localized defaults and document metadata. Never copy these directories into an SSG output or public asset disk. Public search documents already use an explicit public collection allowlist; application entries are also unpublished, unrouted and non-augmenting. The Content API remains disabled in the existing configuration.

## CV output

The saved application's private CV can be downloaded from the editor. The CP-only route `GET /cp/applications/{entry-id}/cv.pdf` resolves a detached overlay through `CvProfileContentResolver`, then uses `CvViewModel`, the established LaTeX template and `CvPdfRenderer`. It uses private temporary compilation and deletes the returned PDF after sending. It creates no CV capability and does not use the public PDF cache. The existing LuaLaTeX installation and fonts are required.

The [PDF workflow](application-pdf-workflow.md) adds composed exports, private previews, content-aware caching and optional revocable recipient links. It sends no mail and applies no QES.

## Verification

Run `STATAMIC_PRO_ENABLED=true php artisan test --compact`. Pro is enabled for the test process because tests create temporary users. `ApplicationManagementTest` exercises actual CP create/edit/update requests, validation, encryption, date reset, access denial, attachment inheritance/order, reusable blocks and public-output leakage. Existing CV resolution and PDF tests cover the shared pipeline. No production data or raw signature is needed for these checks.

PDFs already selected by an application or the attachment defaults cannot be deleted from the private asset container. Remove the reference, save the record, then delete the asset. This prevents a later upload using the same path from silently replacing the selected document.
