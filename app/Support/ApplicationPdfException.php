<?php

namespace App\Support;

final class ApplicationPdfException extends \RuntimeException
{
    public function __construct(string $stage = 'export', ?string $diagnosticId = null)
    {
        $reference = $diagnosticId ? ' Fehlerreferenz: '.$diagnosticId.'.' : '';
        parent::__construct('PDF-Schritt ['.$stage.']: Die Bewerbungs-PDF konnte nicht erstellt werden. Bitte Daten und PDF-Dokumente prüfen oder die Administration kontaktieren.'.$reference);
    }
}
