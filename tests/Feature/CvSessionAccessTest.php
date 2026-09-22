<?php
namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_exchange_cleans_url_and_authorizes_only_its_profile_and_pdf(): void
    {
        $token = CvCapabilities::issueTemporary('/cv/airbus-26')['token'];
        $this->get('/cv/airbus-26?cv='.$token)->assertRedirect('/cv/airbus-26')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->postJson('/cv/private-data', ['path'=>'/cv/airbus-26'])->assertOk();
        $this->postJson('/cv/private-data', ['path'=>'/cv'])->assertNotFound();
        $pdf = tempnam(sys_get_temp_dir(), 'cv-test'); file_put_contents($pdf, '%PDF-1.4');
        $this->mock(CvPdfRenderer::class)->shouldReceive('render')->once()->with('airbus-26', true)->andReturn($pdf);
        $this->get('/cv/airbus-26/pdf')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        @unlink($pdf);
    }

    public function test_expiration_and_revocation_end_existing_sessions(): void
    {
        $temporary = CvCapabilities::issueTemporary('/cv', 1)['token'];
        $this->get('/cv?cv='.$temporary)->assertRedirect('/cv');
        $this->travel(2)->minutes();
        $this->postJson('/cv/private-data', ['path'=>'/cv'])->assertNotFound();
        $this->get('/cv?cv='.$temporary)->assertNotFound();
        $permanent = CvCapabilities::permanent('/cv/airbus-26')['token'];
        $this->get('/cv/airbus-26?cv='.$permanent)->assertRedirect('/cv/airbus-26');
        CvCapabilities::revokePermanent('/cv/airbus-26');
        $this->postJson('/cv/private-data', ['path'=>'/cv/airbus-26'])->assertNotFound();
        $this->get('/cv/airbus-26?cv='.$permanent)->assertNotFound();
    }

    public function test_invalid_and_wrong_scope_tokens_do_not_authorize(): void
    {
        $token = CvCapabilities::issueTemporary('/cv')['token'];
        $this->get('/cv/airbus-26?cv='.$token)->assertNotFound();
        $this->get('/cv?cv=invalid')->assertNotFound();
    }

    public function test_legacy_uuid_route_exchanges_and_redirects(): void
    {
        $path = '/cv/04d5407d-e18b-49e6-8ac5-9f54146b88cc';
        $token = CvCapabilities::issueTemporary($path)['token'];
        $this->get($path.'?cv='.$token)->assertRedirect('/cv/airbus-26');
        $this->postJson('/cv/private-data', ['path'=>'/cv/airbus-26'])->assertOk();
    }


    public function test_profile_timeline_inherit_show_and_hide_follow_base_setting(): void
    {
        $global = \Statamic\Facades\GlobalSet::find('cv')->inDefaultSite();
        $original = $global->data()->all();
        $profile = \Statamic\Facades\Entry::find('04d5407d-e18b-49e6-8ac5-9f54146b88cc');
        $previous = $profile->get('interactive_timeline');
        try {
            $global->set('interactive_timeline', false);
            $profile->set('interactive_timeline', 'inherit');
            $this->get('/cv/airbus-26')->assertDontSee('data-cv-explore');
            $profile->set('interactive_timeline', 'show');
            $this->get('/cv/airbus-26')->assertSee('data-cv-explore');
            $profile->set('interactive_timeline', 'hide');
            $this->get('/cv/airbus-26')->assertDontSee('data-cv-explore');
        } finally { $global->data($original); $profile->set('interactive_timeline', $previous); }
    }

    public function test_titles_and_about_and_profile_visibility(): void
    {
        $response = $this->get('/cv');
        preg_match('#<title>(.*?)</title>#s', $response->getContent(), $title);
        $this->assertSame('Jack Ruder · Lebenslauf', $title[1]);
        $response->assertSee('ich bin jack, jhack');
        $this->get('/cv/airbus-26')->assertSee('Jack Ruder · Lebenslauf · Airbus 2027');
        $profile = \Statamic\Facades\Entry::find('04d5407d-e18b-49e6-8ac5-9f54146b88cc');
        $original = $profile->get('interactive_timeline');
        try { $profile->set('interactive_timeline', 'hide'); $this->get('/cv/airbus-26')->assertDontSee('data-cv-explore'); }
        finally { $profile->set('interactive_timeline', $original); }
    }
}
