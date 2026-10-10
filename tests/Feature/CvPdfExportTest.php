<?php

namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use App\Support\CvViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CvPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_export_returns_a_pdf_from_the_canonical_renderer(): void
    {
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with(null, false, null)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $response = $this->get('/cv/pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_authorized_profile_export_forwards_only_a_valid_profile_capability(): void
    {
        $token = CvCapabilities::issueTemporary('/cv/jobmesse-26')['token'];
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with('jobmesse-26', true, $token)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->get('/cv/jobmesse-26/pdf?token='.$token)->assertNotFound();
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $token])->assertOk();
        $this->get('/cv/jobmesse-26/pdf')->assertOk();
    }

    public function test_export_rejects_an_invalid_capability_without_starting_lualatex(): void
    {
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldNotReceive('render');
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->get('/cv/pdf?token=invalid')->assertNotFound();
    }

    public function test_generated_public_pdf_is_two_page_a4_without_timeline_and_links_to_the_cv_on_each_page(): void
    {
        $cv = app(CvViewModel::class)->make();
        $this->assertSame('#5adbbd', $cv['accent']);
        $pdf = app(CvPdfRenderer::class)->render();

        try {
            $text = $this->runPdfTool(['pdftotext', '-layout', $pdf, '-']);
            $info = $this->runPdfTool(['pdfinfo', $pdf]);
            $urls = $this->runPdfTool(['pdfinfo', '-url', $pdf]);
            $canonicalUrl = $cv['canonical_url'];
            $visibleUrl = preg_replace('/^https?:\/\//i', '', $canonicalUrl);

            preg_match('/^Pages:\s+(\d+)$/m', $info, $pageMatch);
            preg_match('/^Page size:\s+(.+)$/m', $info, $sizeMatch);
            $pageCount = (int) ($pageMatch[1] ?? 0);
            $pages = array_values(array_filter(explode("\f", trim($text)), fn ($page) => trim($page) !== ''));
            $normalizedPages = array_map(fn ($page) => preg_replace('/\s+/u', ' ', $page), $pages);

            $this->assertSame(2, $pageCount);
            $this->assertCount($pageCount, $pages);
            $this->assertMatchesRegularExpression('/595\.\d+ x 841\.\d+ pts \(A4\)/', $sizeMatch[1] ?? '');
            $this->assertStringNotContainsString('Stationen', $text);
            foreach ($normalizedPages as $page) {
                $this->assertStringContainsString($visibleUrl, $page);
            }
            $this->assertStringNotContainsString('https://jack.djl.foundation/cv', preg_replace('/\s+/u', '', $text));
            $this->assertGreaterThanOrEqual($pageCount + 1, substr_count(preg_replace('/\s+/u', '', $text), $visibleUrl));

            preg_match_all('/^\s*(\d+)\s+Annotation\s+https:\/\/jack\.djl\.foundation\/cv\s*$/m', $urls, $annotationPages);
            $this->assertSame(range(1, $pageCount), array_values(array_unique(array_map('intval', $annotationPages[1]))));
            foreach (['website', 'github', 'codeberg'] as $handle) {
                $this->assertNotEmpty($cv['contact'][$handle]);
                $this->assertStringContainsString($cv['contact'][$handle], $urls);
            }
            foreach (['experience', 'education', 'projects', 'publications'] as $section) {
                $this->assertNotEmpty($cv[$section]);
                foreach ($cv[$section] as $entry) {
                    $this->assertStringContainsString($entry['url'], $urls);
                }
            }
        } finally {
            @unlink($pdf);
        }
    }

    public function test_generated_profile_pdf_keeps_its_exact_accent_and_profile_canonical_link(): void
    {
        $cv = app(CvViewModel::class)->make('jobmesse-26');
        $this->assertSame('#5adbbd', $cv['accent']);
        $latex = view('latex.cv', compact('cv'))->render();
        $this->assertStringContainsString('\definecolor{accent}{HTML}{5ADBBD}', $latex);
        $this->assertStringContainsString('\colorlet{linkaccent}{accent}', $latex);

        $pdf = app(CvPdfRenderer::class)->render('jobmesse-26');

        try {
            $text = preg_replace('/\s+/u', '', $this->runPdfTool(['pdftotext', '-layout', $pdf, '-']));
            $urls = $this->runPdfTool(['pdfinfo', '-url', $pdf]);
            $visibleUrl = preg_replace('/^https?:\/\//i', '', $cv['canonical_url']);

            $this->assertStringContainsString($visibleUrl, $text);
            $this->assertStringNotContainsString($cv['canonical_url'], $text);
            $this->assertStringContainsString($cv['canonical_url'], $urls);
            $this->assertStringNotContainsString('#cv=', $urls);
        } finally {
            @unlink($pdf);
        }
    }

    private function runPdfTool(array $command): string
    {
        $process = new Process($command);
        $process->mustRun();

        return $process->getOutput();
    }

    private function fakePdf(): string
    {
        $path = storage_path('framework/testing/'.bin2hex(random_bytes(8)).'.pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    }
}
