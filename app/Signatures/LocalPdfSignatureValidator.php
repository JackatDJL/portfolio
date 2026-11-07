<?php

namespace App\Signatures;

use Symfony\Component\Process\Process;

final class LocalPdfSignatureValidator implements SignatureValidator
{
    public function validate(string $unsignedPdf, string $signedPdf): array
    {
        $process = new Process([
            config('applications.python'), base_path('scripts/application-signatures.py'), 'validate',
            $unsignedPdf, $signedPdf, config('signatures.trust_roots_file') ?: '-',
        ]);
        try {
            $process->setTimeout((int) config('signatures.validation_timeout'))->run();
            if (! $process->isSuccessful() || strlen($process->getOutput()) > 65536) {
                throw new SignatureWorkflowException;
            }
            $report = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
            if (($report['integrity'] ?? '') !== 'valid' || ($report['document_binding'] ?? '') !== 'exact_incremental' || empty($report['signatures'])) {
                throw new SignatureWorkflowException;
            }
            // This validator cannot assess EU trusted-list qualification or long-term evidence.
            $report['qes_verified'] = false;
            $report['qualification'] = 'not_assessed';
            $report['long_term_validation'] = 'not_assessed_offline';
            $report['revocation'] = 'not_checked_offline';

            return $report;
        } catch (\Throwable) {
            throw new SignatureWorkflowException('Import abgelehnt: Signatur, Dokumentbindung oder unveränderte Inhalte konnten nicht bestätigt werden.');
        }
    }
}
