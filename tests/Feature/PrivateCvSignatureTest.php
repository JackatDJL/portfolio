<?php

namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\PrivateHandwrittenSignature;
use App\Support\SignatureSecurityPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\GlobalSet;
use Symfony\Component\Process\Process;
use Tests\Support\ApplicationPdfFixture;
use Tests\Support\HandwrittenSignatureFixture;
use Tests\TestCase;

class PrivateCvSignatureTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationPdfFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new ApplicationPdfFixture;
    }

    protected function tearDown(): void
    {
        $this->fixture->cleanup();
        parent::tearDown();
    }

    public function test_synthetic_pattern_is_configurable_and_never_depends_on_signature_bytes(): void
    {
        $patterns = app(SignatureSecurityPattern::class);
        $signature = app(PrivateHandwrittenSignature::class);
        $first = HandwrittenSignatureFixture::png();
        $signature->save('data:image/png;base64,'.base64_encode($first), HandwrittenSignatureFixture::strokes());
        $patternBefore = $patterns->render('guilloche', 'blue', 'balanced');

        $signature->save('data:image/png;base64,'.base64_encode(HandwrittenSignatureFixture::png(variant: 1)), HandwrittenSignatureFixture::strokes());
        $patternAfter = $patterns->render('guilloche', 'blue', 'balanced');

        $this->assertSame($patternBefore, $patternAfter);
        $this->assertStringContainsString('stroke="#315CA8"', $patternBefore);
        $this->assertNotSame($patternBefore, $patterns->render('rosette', 'violet', 'fine'));
        $this->assertStringNotContainsString(base64_encode($first), $patternBefore);
        $this->assertStringNotContainsString('data:image', $patternBefore);
    }

    public function test_public_cv_uses_only_synthetic_pattern_and_private_signature_endpoint_checks_scope_and_revocation(): void
    {
        $cv = GlobalSet::find('cv')->inDefaultSite();
        $cv->set('signature_pattern_enabled', true);
        $cv->set('signature_pattern_style', 'guilloche');
        $cv->set('signature_pattern_color', 'teal');
        $cv->set('signature_pattern_density', 'fine');
        $cv->set('private_cv_signature_enabled', true);
        $png = HandwrittenSignatureFixture::png();
        app(PrivateHandwrittenSignature::class)->save('data:image/png;base64,'.base64_encode($png), HandwrittenSignatureFixture::strokes());

        $public = $this->get('/cv/jobmesse-26')->assertOk();
        $public->assertSee('data-signature-pattern', false)
            ->assertDontSee('kein Fälschungsschutz', false)
            ->assertDontSee(base64_encode($png));
        $this->postJson('/cv/private-signature', ['path' => '/cv/jobmesse-26'])->assertNotFound();

        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();
        $authorizedPage = $this->get('/cv/jobmesse-26')->assertOk();
        $authorizedPage->assertDontSee(base64_encode($png))
            ->assertSee('data-cv-private-signature-meta', false)
            ->assertSee('Stade');
        $privateImage = $this->post('/cv/private-signature', ['path' => '/cv/jobmesse-26']);
        $privateImage->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertMatchesRegularExpression('/\bprivate\b.*\bno-store\b|\bno-store\b.*\bprivate\b/', (string) $privateImage->headers->get('Cache-Control'));
        $this->assertSame($png, $privateImage->getContent());

        DB::table('cv_access_tokens')->where('identifier', $grant['identifier'])->update(['revoked_at' => now()]);
        $this->post('/cv/private-signature', ['path' => '/cv/jobmesse-26'])->assertNotFound();

        $disabledGrant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $disabledGrant['token']])->assertOk();
        $cv->set('private_cv_signature_enabled', false);
        $this->post('/cv/private-signature', ['path' => '/cv/jobmesse-26'])->assertNotFound();
    }

    public function test_authorized_private_signature_endpoint_works_with_csrf_middleware_and_without_fetch_site_header(): void
    {
        $cv = GlobalSet::find('cv')->inDefaultSite();
        $cv->set('private_cv_signature_enabled', true);
        $png = HandwrittenSignatureFixture::png();
        app(PrivateHandwrittenSignature::class)->save('data:image/png;base64,'.base64_encode($png), HandwrittenSignatureFixture::strokes());

        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();

        // Laravel's test environment normally skips request forgery checks. Use a
        // production-like environment to exercise the configured middleware and
        // the same header-less request sent by the private CV page.
        $previousEnvironment = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->call('POST', '/cv/private-signature', ['path' => '/cv/jobmesse-26'])
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png');
        } finally {
            $this->app['env'] = $previousEnvironment;
        }
    }

    public function test_authorized_cv_pdf_places_signature_after_content_on_a4_without_public_leakage(): void
    {
        $cv = GlobalSet::find('cv')->inDefaultSite();
        $cv->set('private_cv_signature_enabled', true);
        $cv->set('private_cv_signature_width_mm', 46);
        $cv->set('private_cv_signature_baseline_mm', 0);
        $cv->set('private_cv_signature_alignment', 'right');
        $cv->set('print_place', 'Synthetic City');
        $cv->set('print_date', '6. November 2026');
        app(PrivateHandwrittenSignature::class)->save(
            'data:image/png;base64,'.base64_encode(HandwrittenSignatureFixture::png()),
            HandwrittenSignatureFixture::strokes(),
        );

        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();
        $signed = $this->get('/cv/jobmesse-26/pdf')->assertOk();
        $signedBytes = $signed->streamedContent();
        [$signedText, $signedImages, $signedInfo] = $this->inspectPdf($signedBytes);

        $public = $this->get('/cv/jobmesse-26/pdf?public=1')->assertOk();
        [$publicText, $publicImages, $publicInfo] = $this->inspectPdf($public->streamedContent());

        $this->assertStringContainsString('Synthetic City, 6. November 2026', preg_replace('/\s+/u', ' ', $signedText));
        $this->assertStringContainsString('Erika Muster', $signedText);
        $this->assertMatchesRegularExpression('/^\s*\d+\s+\d+\s+image\s+480\s+140\b/m', $signedImages);
        $this->assertDoesNotMatchRegularExpression('/^\s*\d+\s+\d+\s+image\s+480\s+140\b/m', $publicImages);
        $this->assertStringNotContainsString('Synthetic City, 6. November 2026', $publicText);
        $this->assertSame($publicInfo['pages'], $signedInfo['pages']);
        $this->assertStringContainsString('A4', $signedInfo['page_size']);
    }

    public function test_private_cv_typed_mode_needs_no_graphical_source(): void
    {
        $cv = GlobalSet::find('cv')->inDefaultSite();
        $cv->set('private_cv_signature_enabled', true);
        $cv->set('private_cv_signature_mode', 'typed');
        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();
        $this->postJson('/cv/private-data', ['path' => '/cv/jobmesse-26'])->assertOk()->assertJsonPath('signature_mode', 'typed');
        $this->post('/cv/private-signature', ['path' => '/cv/jobmesse-26'])->assertNotFound();
        $response = $this->get('/cv/jobmesse-26/pdf')->assertOk();
        $path = $this->fixture->root.'/typed-cv.pdf';
        file_put_contents($path, $response->streamedContent());
        $fonts = (new Process(['pdffonts', $path]))->mustRun()->getOutput();
        $this->assertStringContainsString('MomoSignature', $fonts);
        $this->assertFalse(app(PrivateHandwrittenSignature::class)->exists());
    }

    private function inspectPdf(string $bytes): array
    {
        $path = tempnam($this->fixture->root, 'signature-layout-');
        file_put_contents($path, $bytes);
        try {
            $text = (new Process(['pdftotext', '-layout', $path, '-']))->mustRun()->getOutput();
            $images = (new Process(['pdfimages', '-list', $path]))->mustRun()->getOutput();
            $info = (new Process(['pdfinfo', '-box', $path]))->mustRun()->getOutput();
            preg_match('/^Pages:\s+(\d+)$/m', $info, $pages);
            preg_match('/^Page size:\s+(.+)$/m', $info, $size);

            return [$text, $images, ['pages' => (int) ($pages[1] ?? 0), 'page_size' => trim($size[1] ?? '')]];
        } finally {
            @unlink($path);
        }
    }
}
