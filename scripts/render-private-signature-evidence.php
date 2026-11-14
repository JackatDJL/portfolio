<?php

use App\Support\ApplicationLetter;
use App\Support\CvPdfRenderer;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;
use Tests\Support\HandwrittenSignatureFixture;

// Render only fabricated content and an in-memory synthetic signature.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath($argv[1] ?? '');
$repository = realpath(__DIR__.'/..');
if (! $directory || ! is_dir($directory) || $directory === $repository || str_starts_with($directory, $repository.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Pass an existing evidence directory outside the checkout.\n");
    exit(1);
}

$runtime = $directory.'/runtime';
mkdir($runtime, 0700, true);
config([
    'applications.handwritten_signature_path' => $runtime.'/signature-source',
    'applications.work_path' => $runtime.'/work',
    'cv.private_pdf_root' => $runtime.'/compiled',
]);
mkdir($runtime.'/work', 0700, true);

$signature = app(PrivateHandwrittenSignature::class);
$signature->save('data:image/png;base64,'.base64_encode(HandwrittenSignatureFixture::png()), HandwrittenSignatureFixture::strokes());
$signatureFile = $runtime.'/work/signature.png';
$signatureSource = $signature->materialize($signatureFile);
$renderer = app(CvPdfRenderer::class);
$temporaryPdfs = [];
$validation = [];

try {
    $cv = [
        'name' => 'Erika Muster', 'location' => 'Musterstadt', 'profile' => null,
        'accent' => '#315ca8', 'interactive_url' => 'https://example.invalid/cv', 'photo' => null,
        'contact' => [
            'public_email' => 'erika@example.invalid', 'email' => null, 'phone' => null, 'address' => null,
            'website' => 'https://example.invalid', 'github' => null, 'codeberg' => null, 'social_links' => [],
        ],
        'about' => 'Synthetische Person für den PDF-Layouttest.', 'knowledge' => [], 'soft_skills' => [],
        'experience' => [[
            'title' => 'Technisches Beispielprojekt', 'period' => '2025–2026', 'organisation' => 'Beispielwerkstatt',
            'url' => 'https://example.invalid/projekt', 'summary' => 'Ausschließlich erfundener Text zur Prüfung von Typografie und Umbruch.',
        ]],
        'education' => [], 'projects' => [], 'publications' => [['title' => 'Beispiel-Veröffentlichung', 'period' => '2026', 'type' => 'Article', 'url' => 'https://example.invalid/article']], 'milestones' => [],
        'signature_image' => $signatureSource['path'], 'signature_digest' => $signatureSource['sha256'],
        'signature_width_mm' => 46, 'signature_baseline_mm' => 0, 'signature_alignment' => 'left',
        'signed_by' => 'Erika Muster', 'signed_place' => 'Musterstadt', 'signed_date' => '6. November 2026',
    ];
    $temporaryPdfs[] = $renderer->renderViewModel($cv);

    $letter = [
        'sender' => 'Erika Muster', 'sender_address_lines' => ['Musterweg 1', '12345 Musterstadt'],
        'recipient' => ['Beispiel Universität', 'Personalabteilung', 'Beispielstraße 2', '54321 Beispielstadt'],
        'date_line' => 'Musterstadt, den 6. November 2026', 'phone' => '', 'email' => 'erika@example.invalid',
        'documents' => [], 'accent' => '#315ca8', 'subject' => 'Bewerbung um ein Beispielpraktikum',
        'salutation' => 'Sehr geehrte Damen und Herren,',
        'body' => app(ApplicationLetter::class)->nodes([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Dies ist ein synthetischer Anschreibentext. Er enthält keine echten Kontaktdaten oder Qualifikationen.']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Der Link '], ['type' => 'text', 'text' => 'Beispiel', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.invalid/info']]]], ['type' => 'text', 'text' => ' bleibt anklickbar.']]],
        ]),
        'closing' => 'Mit freundlichen Grüßen', 'signature' => '', 'signature_image' => $signatureSource['path'],
        'signature_width_mm' => 46, 'signature_baseline_mm' => 0, 'signature_alignment' => 'left',
    ];
    $letterTex = view('latex.application-letter', compact('letter'))->render();
    $temporaryPdfs[] = $renderer->compile($letterTex, passes: 2);

    $cv['signature_image'] = null;
    $cv['signature_text'] = $cv['name'];
    $temporaryPdfs[] = $renderer->renderViewModel($cv);
    $letter['signature_image'] = null;
    $letter['signature'] = $letter['sender'];
    $temporaryPdfs[] = $renderer->compile(view('latex.application-letter', compact('letter'))->render(), passes: 2);

    $names = ['cv.pdf', 'cover-letter.pdf', 'cv-typed.pdf', 'cover-letter-typed.pdf'];
    foreach ($temporaryPdfs as $index => $source) {
        $pdf = $directory.'/'.$names[$index];
        if (! copy($source, $pdf)) {
            throw new RuntimeException('Could not copy a synthetic PDF to the evidence directory.');
        }
        chmod($pdf, 0600);
        $prefix = $directory.'/'.pathinfo($pdf, PATHINFO_FILENAME).'-page';
        (new Process(['pdftoppm', '-scale-to', '1400', '-png', $pdf, $prefix], null, null, null, 60))->mustRun();
        $info = (new Process(['pdfinfo', '-box', $pdf], null, null, null, 10))->mustRun()->getOutput();
        $images = (new Process(['pdfimages', '-list', $pdf], null, null, null, 10))->mustRun()->getOutput();
        $text = (new Process(['pdftotext', '-layout', $pdf, '-'], null, null, null, 10))->mustRun()->getOutput();
        preg_match('/^Pages:\s+(\d+)$/m', $info, $pages);
        preg_match('/^Page size:\s+(.+)$/m', $info, $size);
        if (! str_contains($size[1] ?? '', 'A4') || ($index < 2 && ! preg_match('/\bimage\s+480\s+140\b/', $images))) {
            throw new RuntimeException('Synthetic signature PDF failed its A4 or embedded-image checks.');
        }
        if ($index >= 2) {
            $fonts = (new Process(['pdffonts', $pdf]))->mustRun()->getOutput();
            if (! str_contains($fonts, 'MomoSignature')) {
                throw new RuntimeException('Typed signature font is missing.');
            }
        }
        if ($index === 0 && (! str_contains($text, 'Musterstadt') || ! str_contains($text, '6. November 2026'))) {
            throw new RuntimeException('Synthetic CV PDF is missing its signed-by date or location.');
        }
        if ($index === 1 && (! str_contains($text, 'Mit freundlichen Grüßen') || ! str_contains($text, 'Erika Muster'))) {
            throw new RuntimeException('Synthetic cover letter PDF is missing its closing or typed name.');
        }
        $validation[$names[$index]] = ['pages' => (int) ($pages[1] ?? 0), 'page_size' => trim($size[1] ?? ''), 'synthetic_signature_embedded' => true];
    }

    file_put_contents($directory.'/validation.json', json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Rendered synthetic CV and cover-letter PDFs with A4 and embedded-image checks.\n";
} finally {
    foreach ($temporaryPdfs as $pdf) {
        @unlink($pdf);
    }
    $signature->delete();
    @unlink($signatureFile);
    $remove = function (string $path) use (&$remove): void {
        if (is_dir($path) && ! is_link($path)) {
            foreach (scandir($path) ?: [] as $child) {
                if ($child !== '.' && $child !== '..') {
                    $remove($path.'/'.$child);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    };
    $remove($runtime);
}
