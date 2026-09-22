<?php

namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CvPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_export_returns_a_pdf_from_the_canonical_renderer(): void
    {
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with(null, false)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $response = $this->get('/cv/pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_authorized_profile_export_forwards_only_a_valid_profile_capability(): void
    {
        $token = CvCapabilities::issueTemporary('/cv/airbus-26')['token'];
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with('airbus-26', true)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->get('/cv/airbus-26/pdf?token='.$token)->assertRedirect('/cv/airbus-26/pdf');
        $this->get('/cv/airbus-26/pdf')->assertOk();
    }

    public function test_export_rejects_an_invalid_capability_without_starting_lualatex(): void
    {
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldNotReceive('render');
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->get('/cv/pdf?token=invalid')->assertNotFound();
    }

    private function fakePdf(): string
    {
        $path = storage_path('framework/testing/'.bin2hex(random_bytes(8)).'.pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    }
}
