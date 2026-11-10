# Private application signatures

The application PDF exporter remains the only rendering/composition pipeline. CV rendering and CV authorization are unchanged. Electronic signatures apply only to a frozen, complete composite, after cover letter, freshly resolved private CV and ordered attachments have been composed. The existing typed visual signature is decorative and is never presented as a cryptographic signature or QES.

## Operator workflow

1. Install `requirements-pdf.txt` in the existing private PDF Python environment and run the new Laravel migration through the normal deployment procedure. No production migration is performed by this PR.
2. Save the application, check its complete unsigned preview, then choose **Endgültige PDF einfrieren** in the Statamic application editor. Download that version's unsigned original.
3. Sign that exact file externally. Use incremental PAdES signing, without rewriting, printing, flattening, editing or merging it. The importer deliberately rejects broader changes. Existing embedded signatures and application forms are unsupported at preparation time; resolve these before paying a signing provider.
4. Select the signed PDF beside that same version and import it. Import does not activate delivery. Validation runs locally, with certificate URL fetching disabled. Invalid signatures, mismatched originals, unsafe incremental modifications and non-signature field changes are rejected.
5. Review the separate integrity, certificate-path and qualification results. Explicitly select the version for delivery only after assessing those limitations. Preview always generates the current unsigned PDF; canonical export/recipient delivery uses the selected signed version. Changed application/CV/documents cause a digest mismatch and block signed delivery; there is no silent unsigned fallback. Explicitly select unsigned delivery or prepare/sign a new version.

Unsigned mode is the default. Archived versions remain available privately and are not overwritten. A valid signature with an untrusted certificate can be archived and explicitly selected; the system does not claim the signer identity or QES has been established. Recipient links retain the existing application-specific authorization and revocation checks; they do not grant access to signature administration or archive downloads.

## Validation scope

`SignatureValidator` is independent of `SignatureProvider`. The supplied pyHanko adapter validates the cryptographic signature, coverage and conservative incremental-update policy against the exact frozen bytes. Original prefix matching alone is insufficient: a later revision can replace page content. Changes to signed artifacts are never repaired or normalized after signing.

**This implementation never verifies QES qualification.** It does not consult EU trusted lists, establish qualified certificate/QSCD status, establish a legally reliable signing time or check online revocation. Certificate trust defaults to untrusted. `APPLICATION_SIGNATURE_TRUST_ROOTS` may point to an administrator-managed PEM trust bundle; pinning a self-signed certificate establishes neither QES nor legal identity. Offline soft-fail trust evaluation must not be interpreted as confirmed non-revocation. Signature metadata reports `qualification=not_assessed`, `qes_verified=false`, `revocation=not_checked_offline` and `long_term_validation=not_assessed_offline` explicitly.

LTV, LT/LTA augmentation and archival renewal require a separate validator/post-processing implementation with controlled trust lists, revocation evidence and qualified timestamps. DSS is a possible future validation adapter, not an identity provider or signing credential. Do not upload personal documents to public DSS demos. Any future augmentation must create a separate version and revalidate it; never mutate the retained unsigned/signed originals.

## Provider extension and sandbox

`SignatureProvider` exposes explicit availability, remote-signing, QES, sandbox, upload/hash and LTV capabilities. No live provider adapter is enabled: the [official-source comparison](application-signature-provider-research.md) did not establish an accessible, affordable private-individual API or eligibility at age 17. Unsupported provider configuration fails closed. Future adapters must use server-side secrets, have explicit exact-digest authorization, idempotency and reconciliation for ambiguous remote outcomes, and separate initiation, user authentication and completion. Do not reuse the sandbox's synchronous workflow for paid asynchronous requests.

Default `APPLICATION_SIGNATURE_PROVIDER=unavailable` makes no provider requests. For local/testing only, set `APPLICATION_SIGNATURE_PROVIDER=sandbox` and `APPLICATION_SIGNATURE_SANDBOX=true`. The CP offers a separately confirmed local test action. It generates a disposable in-memory self-signed certificate, makes no network/paid requests and stores no signing key. Sandbox artifacts are visibly marked **not QES** and cannot be delivered through recipient access. Production rejects sandbox even when configuration enables it. External imports are evaluated by signature evidence, not by provenance labels or scanned appearance.

Preview, export, save, autosave and recipient access never initiate signing. There is no email sender. Existing `ApplicationPdfPostProcessor` remains unchanged; signing is explicitly outside rendering/cache generation.

## Privacy and operations

Artifacts live beneath `storage/app/private/application-signatures`, with opaque application hashes, UUID version directories, 0700 directories and 0600 PDF files. No filenames from uploads, signatures, keys or certificate reports enter public assets or Statamic content. Verification metadata is Laravel-encrypted in the database; retain `APP_KEY` securely with encrypted backups. Checksums detect changed/missing stored files before use. CP endpoints require a Statamic super user and normal CSRF protection for mutations; responses use private/no-store and noindex headers. Parser errors are sanitized. Configure PHP/web-server upload limits consistently with the 100 MiB server validation limit and keep the parser environment patched.

Private storage permissions are defense in depth, not protection against a compromised server/admin or a public web-server alias. Do not expose this directory through any asset container or symlink. Retain only necessary applications and verification records; backups and archive retention need an operator policy. No automated deletion or public publication is implemented. A visible handwritten/scanned signature can be copied; no copy-prevention claim is made.

At age 17, provider contract eligibility and legal consent remain unresolved. Do not equate availability of German online eID from age 16 with eligibility for a particular QES service or binding contract. Obtain provider confirmation and any required guardian consent before using a paid service. The research document distinguishes verified provider terms from unknowns. This software and its status labels are not a legal opinion on a particular application or signature.
