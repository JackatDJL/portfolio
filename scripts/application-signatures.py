#!/usr/bin/env python3
"""Local-only PDF signature checks. No AIA/OCSP/TSA fetching and no QES inference."""
import hashlib
import json
import logging
import os
import resource
import sys
from datetime import datetime, timedelta, timezone
from importlib.metadata import version

logging.disable(logging.CRITICAL)


def sandbox(source, target):
    """Explicit local test signing only; the disposable key never leaves process memory."""
    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.x509.oid import NameOID
    from asn1crypto import keys, x509 as asn1_x509
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
    from pyhanko.sign import signers
    from pyhanko_certvalidator.registry import SimpleCertificateStore

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'Portfolio Sandbox - NOT QES')])
    now = datetime.now(timezone.utc)
    cert = (x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(key.public_key())
            .serial_number(x509.random_serial_number()).not_valid_before(now - timedelta(minutes=1))
            .not_valid_after(now + timedelta(days=1))
            .add_extension(x509.BasicConstraints(ca=False, path_length=None), critical=True)
            .add_extension(x509.KeyUsage(True, True, False, False, False, False, False, None, None), critical=True)
            .sign(key, hashes.SHA256()))
    signer = signers.SimpleSigner(
        signing_cert=asn1_x509.Certificate.load(cert.public_bytes(serialization.Encoding.DER)),
        signing_key=keys.PrivateKeyInfo.load(key.private_bytes(serialization.Encoding.DER, serialization.PrivateFormat.PKCS8, serialization.NoEncryption())),
        cert_registry=SimpleCertificateStore(),
    )
    with open(source, 'rb') as original, open(target, 'xb') as output:
        os.chmod(target, 0o600)
        signers.PdfSigner(signers.PdfSignatureMetadata(field_name='SandboxSignature', reason='LOCAL SANDBOX ONLY - NOT QES'), signer).sign_pdf(IncrementalPdfFileWriter(original), output=output)


def validate(original_path, signed_path, trust_path):
    from pyhanko.pdf_utils.reader import PdfFileReader
    from pyhanko.sign.diff_analysis import DEFAULT_DIFF_POLICY, ModificationLevel, SuspiciousModification
    from pyhanko.sign.validation import validate_pdf_signature
    from pyhanko.sign.validation.status import SignatureCoverageLevel
    from pyhanko.keys import load_certs_from_pemder
    from pyhanko_certvalidator import ValidationContext

    # Exact prefix binding alone is insufficient: incremental updates can replace pages.
    with open(original_path, 'rb') as original, open(signed_path, 'rb') as signed:
        original_size = os.fstat(original.fileno()).st_size
        if os.fstat(signed.fileno()).st_size <= original_size:
            raise ValueError('Not an incremental signature')
        while block := original.read(1024 * 1024):
            if signed.read(len(block)) != block:
                raise ValueError('Original document mismatch')
        original.seek(0)
        signed.seek(0)
        base = PdfFileReader(original, strict=True)
        reader = PdfFileReader(signed, strict=True)
        if base.embedded_regular_signatures:
            raise ValueError('Original must be unsigned')
        signatures = reader.embedded_regular_signatures
        if not 1 <= len(signatures) <= 8:
            raise ValueError('No regular signatures or too many signatures')
        if reader.xrefs.total_revisions <= base.xrefs.total_revisions:
            raise ValueError('Missing original revision')
        diff = DEFAULT_DIFF_POLICY.review_file(reader, base.xrefs.total_revisions - 1)
        if isinstance(diff, SuspiciousModification) or diff.modification_level > ModificationLevel.FORM_FILLING:
            raise ValueError('Content changed')
        names = {sig.field_name for sig in signatures}
        if not diff.changed_form_fields.issubset(names):
            raise ValueError('Non-signature fields changed')
        roots = list(load_certs_from_pemder([trust_path])) if trust_path != '-' else []
        details = []
        for sig in signatures:
            context = ValidationContext(trust_roots=roots, allow_fetching=False, revocation_mode='soft-fail')
            status = validate_pdf_signature(sig, signer_validation_context=context)
            if status.md_algorithm not in {'sha256', 'sha384', 'sha512', 'sha3_256', 'sha3_384', 'sha3_512'}:
                raise ValueError('Unsupported digest algorithm')
            if not status.intact or not status.valid or not status.docmdp_ok or status.coverage < SignatureCoverageLevel.ENTIRE_REVISION:
                raise ValueError('Signature integrity or coverage failed')
            if status.modification_level is not None and status.modification_level > ModificationLevel.FORM_FILLING:
                raise ValueError('Unsafe post-signature change')
            cert = sig.signer_cert
            details.append({
                'field': sig.field_name, 'intact': bool(status.intact), 'cryptographically_valid': bool(status.valid),
                'certificate_path_trusted': bool(status.trusted),
                'certificate_sha256': hashlib.sha256(cert.dump()).hexdigest(),
                'subject': cert.subject.human_friendly[:2000], 'issuer': cert.issuer.human_friendly[:2000],
                'coverage': status.coverage.name, 'hash_algorithm': status.md_algorithm,
            })
        # No later form filling is accepted after the last approval; only LTA evidence updates.
        last = validate_pdf_signature(signatures[-1], signer_validation_context=ValidationContext(trust_roots=roots, allow_fetching=False))
        if last.coverage != SignatureCoverageLevel.ENTIRE_FILE and last.modification_level > ModificationLevel.LTA_UPDATES:
            raise ValueError('Final revision not approved')
        return {
            'integrity': 'valid', 'document_binding': 'exact_incremental', 'policy': 'portfolio-offline-v1',
            'validator': 'pyHanko', 'validator_version': version('pyHanko'),
            'validated_at': datetime.now(timezone.utc).isoformat(), 'network_access': False,
            'certificate_path': 'trusted' if all(item['certificate_path_trusted'] for item in details) else 'untrusted',
            'qualification': 'not_assessed', 'qes_verified': False, 'revocation': 'not_checked_offline',
            'long_term_validation': 'not_assessed_offline', 'signatures': details,
        }


if __name__ == '__main__':
    try:
        resource.setrlimit(resource.RLIMIT_AS, (768 * 1024 * 1024, 768 * 1024 * 1024))
        if sys.argv[1] == 'check-unsigned':
            from pyhanko.pdf_utils.reader import PdfFileReader
            with open(sys.argv[2], 'rb') as source:
                reader = PdfFileReader(source, strict=True)
                if reader.embedded_regular_signatures or reader.embedded_timestamp_signatures:
                    raise ValueError('Existing signatures')
                form = reader.root['/AcroForm']
                if form.get('/SigFlags') != 3 or form.get('/Fields'):
                    raise ValueError('Unsupported form structure')
        elif sys.argv[1] == 'sandbox':
            sandbox(sys.argv[2], sys.argv[3])
        elif sys.argv[1] == 'validate':
            print(json.dumps(validate(sys.argv[2], sys.argv[3], sys.argv[4]), ensure_ascii=True))
        else:
            raise ValueError('Unsupported operation')
    except Exception:
        # Certificate identities, document text and filenames must not enter logs/responses.
        sys.stderr.write('PDF signature operation failed.\n')
        sys.exit(1)
