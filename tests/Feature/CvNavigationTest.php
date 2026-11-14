<?php

namespace Tests\Feature;

use App\Support\ApplicationCapabilities;
use App\Support\ApplicationData;
use App\Support\CvCapabilities;
use App\Support\CvNavigation;
use App\Support\CvNavigationUrlExcluder;
use App\Support\CvPdfRenderer;
use App\Support\CvViewModel;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\StaticCaching\DefaultUrlExcluder;
use Tests\Support\ApplicationPdfFixture;
use Tests\Support\HandwrittenSignatureFixture;
use Tests\TestCase;

class CvNavigationTest extends TestCase
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

    public function test_detail_return_context_preserves_the_profile_and_existing_authorization(): void
    {
        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();
        $cv = app(CvViewModel::class)->make('jobmesse-26', true, $grant['token']);
        $url = $cv['education'][0]['url'];
        $this->assertStringContainsString('cv_return=%2Fcv%2Fjobmesse-26#cv='.$grant['token'], $url);
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertOk()->assertSee('href="/cv/jobmesse-26"', false);
        $this->get('/cv/jobmesse-26')->assertOk()->assertSee('cv_return=%2Fcv%2Fjobmesse-26', false);
        if ($cv['projects']) {
            $project = $cv['projects'][0]['url'];
            $this->get(parse_url($project, PHP_URL_PATH).'?'.parse_url($project, PHP_URL_QUERY))->assertOk()->assertSee('href="/cv/jobmesse-26"', false)->assertSee('window.__cvExchangeFragment', false);
        }
        $this->postJson('/cv/private-data', ['path' => '/cv/jobmesse-26'])->assertOk();
        $this->postJson('/cv/private-data', ['path' => '/cv'])->assertNotFound();
    }

    public function test_return_context_cannot_redirect_to_arbitrary_urls(): void
    {
        foreach (['https://example.org', '//example.org', '/cv/jobmesse-26#cv=secret', '/cv/jobmesse-26?token=secret', '/cv/exp/test', '/cv/nonexistent-profile'] as $path) {
            $this->assertSame('/cv', CvNavigation::returnPath($path));
        }
        $this->assertSame('/cv/jobmesse-26', CvNavigation::returnPath('/cv/jobmesse-26'));
    }

    public function test_return_context_is_excluded_from_static_caching_without_losing_existing_exclusions(): void
    {
        $excluder = new CvNavigationUrlExcluder(new DefaultUrlExcluder('https://example.invalid', ['/applications*']));
        $this->assertTrue($excluder->isExcluded('https://example.invalid/projekte/example?cv_return=%2Fcv%2Fjobmesse-26'));
        $this->assertTrue($excluder->isExcluded('https://example.invalid/applications/id/cv'));
        $this->assertFalse($excluder->isExcluded('https://example.invalid/projekte/example'));
    }

    public function test_application_cv_links_use_only_scoped_session_and_recheck_revocation(): void
    {
        $fixture = new ApplicationPdfFixture;
        try {
            $fixture->entry->save();
            $id = $fixture->entry->id();
            $path = '/applications/'.$id.'/cv';
            $model = app(CvViewModel::class)->forApplication($fixture->entry);
            $this->assertStringNotContainsString('#cv=', json_encode($model));
            $this->assertStringContainsString($path, $model['interactive_url']);
            $this->assertStringContainsString(http_build_query(['cv_return' => $path], '', '&', PHP_QUERY_RFC3986), $model['education'][0]['url']);
            $this->get($path)->assertNotFound();
            $this->get($path.'/pdf')->assertNotFound();
            app(PrivateHandwrittenSignature::class)->save('data:image/png;base64,'.base64_encode(HandwrittenSignatureFixture::png()));
            $fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 0]]));
            $fixture->entry->save();
            $grant = ApplicationCapabilities::issue($id, 'Navigation test');
            $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $grant['token']])->assertOk();
            $this->get($path)->assertOk()->assertSee('data-cv-private-panel', false)->assertDontSee('Lebenslauf zur Bewerbung');
            $this->postJson('/cv/private-data', ['path' => $path])->assertOk();
            $this->post('/cv/private-signature', ['path' => $path])->assertOk()->assertHeader('Content-Type', 'image/png');
            $pdf = $fixture->root.'/route-fixture.pdf';
            file_put_contents($pdf, '%PDF-1.4');
            $this->mock(CvPdfRenderer::class)->shouldReceive('renderApplication')->once()->andReturn($pdf);
            $this->get($path.'/pdf')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
            ApplicationCapabilities::revoke($id, $grant['id']);
            $this->get($path)->assertNotFound();
            $this->postJson('/cv/private-data', ['path' => $path])->assertNotFound();
            $this->post('/cv/private-signature', ['path' => $path])->assertNotFound();
            $this->get($path.'/pdf')->assertNotFound();
        } finally {
            $fixture->cleanup();
        }
    }
}
