<?php

namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use Mockery;
use Tests\TestCase;

class CvPdfExportTest extends TestCase
{
    public function test_public_export_returns_a_pdf_from_the_canonical_renderer(): void
    {
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with('/cv', null)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $response = $this->postJson('/cv/pdf', ['path' => '/cv', 'token' => '']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_authorized_profile_export_forwards_only_a_valid_profile_capability(): void
    {
        $token = CvCapabilities::issue('/cv/airbus-26');
        $pdf = $this->fakePdf();
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->with('/cv/airbus-26', $token)->andReturn($pdf);
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->postJson('/cv/pdf', ['path' => '/cv/airbus-26', 'token' => $token])->assertOk();
    }

    public function test_export_rejects_an_invalid_capability_without_starting_chromium(): void
    {
        $renderer = Mockery::mock(CvPdfRenderer::class);
        $renderer->shouldNotReceive('render');
        $this->app->instance(CvPdfRenderer::class, $renderer);

        $this->postJson('/cv/pdf', ['path' => '/cv', 'token' => 'invalid'])->assertNotFound();
    }

    private function fakePdf(): string
    {
        $path = storage_path('framework/testing/'.bin2hex(random_bytes(8)).'.pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    }
}
