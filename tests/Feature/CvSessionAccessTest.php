<?php
namespace Tests\Feature;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use App\Support\CvViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Statamic\Facades\GlobalSet;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CvSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_fragment_exchange_authorizes_only_its_profile_and_pdf(): void
    {
        $token = CvCapabilities::issueTemporary('/cv/jobmesse-26')['token'];
        $this->postJson('/cv/token-exchange', ['path'=>'/cv/jobmesse-26', 'token'=>$token])->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/cv/jobmesse-26?cv='.$token)->assertNotFound();
        $this->postJson('/cv/private-data', ['path'=>'/cv/jobmesse-26'])->assertOk();
        $this->postJson('/cv/private-data', ['path'=>'/cv'])->assertNotFound();
        $pdf = tempnam(sys_get_temp_dir(), 'cv-test'); file_put_contents($pdf, '%PDF-1.4');
        $this->mock(CvPdfRenderer::class)->shouldReceive('render')->once()->with('jobmesse-26', true, $token)->andReturn($pdf);
        $this->get('/cv/jobmesse-26/pdf')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        @unlink($pdf);
    }

    public function test_expiration_and_revocation_end_existing_sessions(): void
    {
        $temporary = CvCapabilities::issueTemporary('/cv', 1)['token'];
        $this->postJson('/cv/token-exchange', ['path'=>'/cv', 'token'=>$temporary])->assertOk();
        $this->travel(2)->minutes();
        $this->postJson('/cv/private-data', ['path'=>'/cv'])->assertNotFound();
        $this->postJson('/cv/token-exchange', ['path'=>'/cv', 'token'=>$temporary])->assertNotFound();
        $permanent = CvCapabilities::permanent('/cv/jobmesse-26')['token'];
        $this->postJson('/cv/token-exchange', ['path'=>'/cv/jobmesse-26', 'token'=>$permanent])->assertOk();
        CvCapabilities::revokePermanent('/cv/jobmesse-26');
        $this->postJson('/cv/private-data', ['path'=>'/cv/jobmesse-26'])->assertNotFound();
        $this->postJson('/cv/token-exchange', ['path'=>'/cv/jobmesse-26', 'token'=>$permanent])->assertNotFound();
    }

    public function test_private_pdf_keeps_the_capability_in_its_interactive_fragment_and_public_pdf_does_not(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = $variables->data()->all();
        $email = 'privateCVPdfSentinel@example.invalid';
        $privateValues = [
            'private_email' => $email,
            'phone' => 'PRIVATE-CV-PHONE-SENTINEL',
            'street' => 'PRIVATE-CV-STREET-SENTINEL',
            'house_number' => 'PRIVATE-CV-HOUSE-SENTINEL',
            'postal_code' => 'PRIVATE-CV-POSTAL-SENTINEL',
            'city' => 'PRIVATE-CV-CITY-SENTINEL',
            'country' => 'PRIVATE-CV-COUNTRY-SENTINEL',
            'date_of_birth' => 'PRIVATE-CV-BIRTH-DATE-SENTINEL',
            'place_of_birth' => 'PRIVATE-CV-BIRTH-PLACE-SENTINEL',
        ];
        foreach ($privateValues as $handle => $value) {
            $variables->set($handle, Crypt::encryptString($value));
        }
        $token = CvCapabilities::issueTemporary('/cv/jobmesse-26')['token'];

        try {
            $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $token])->assertOk();
            $private = $this->get('/cv/jobmesse-26/pdf')->assertOk();
            $privateBytes = $private->streamedContent();
            [$privateText, $privateUrls] = $this->inspectPdf($privateBytes);
            $privateVisibleText = preg_replace('/\s+/u', '', $privateText);
            $this->assertStringContainsString($email, $privateVisibleText);
            $this->assertStringContainsString('PRIVATE-CV-PHONE-SENTINEL', $privateVisibleText);
            $this->assertStringContainsString('PRIVATE-CV-STREET-SENTINEL', $privateVisibleText);
            $this->assertStringContainsString('PRIVATE-CV-HOUSE-SENTINEL', $privateVisibleText);
            $authorizedUrl = 'jack.djl.foundation/cv/jobmesse-26#cv='.$token;
            $this->assertStringContainsString($authorizedUrl, $privateVisibleText);
            $this->assertGreaterThanOrEqual(3, substr_count($privateVisibleText, $authorizedUrl));
            $this->assertStringNotContainsString('https://jack.djl.foundation/cv/jobmesse-26', $privateVisibleText);
            $this->assertStringContainsString('#cv='.$token, $privateUrls);
            $this->assertStringNotContainsString('?cv=', $privateUrls);
            $this->assertStringNotContainsString('?token=', $privateUrls);

            $public = $this->get('/cv/jobmesse-26/pdf?public=1')->assertOk();
            [$publicText, $publicUrls] = $this->inspectPdf($public->streamedContent());
            $publicVisibleText = preg_replace('/\s+/u', '', $publicText);
            $this->assertStringContainsString('jack.djl.foundation/cv/jobmesse-26', $publicVisibleText);
            $this->assertStringNotContainsString('https://jack.djl.foundation/cv/jobmesse-26', $publicVisibleText);
            foreach ($privateValues as $value) {
                $this->assertStringNotContainsString($value, $publicText.$publicUrls);
            }
            $this->assertStringNotContainsString($token, $publicText.$publicUrls);
        } finally {
            $variables->data($original);
        }
    }

    public function test_latex_template_keeps_interactive_chronology_out_of_the_printable_cv(): void
    {
        $cv = app(CvViewModel::class)->make();
        $cv['milestones'][] = [
            'title' => 'TIMELINE-ONLY-ENTRY-MUST-NOT-PRINT',
            'date' => '2026',
            'organisation' => '',
            'summary' => '',
            'relations' => [],
        ];

        $latex = view('latex.cv', compact('cv'))->render();

        $this->assertStringNotContainsString('TIMELINE-ONLY-ENTRY-MUST-NOT-PRINT', $latex);
        $this->assertStringNotContainsString('Stationen', $latex);
    }

    public function test_revoking_a_permanent_capability_removes_private_pdf_access(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = $variables->data()->all();
        $email = 'privateCVRevokedSentinel@example.invalid';
        $variables->set('private_email', Crypt::encryptString($email));
        $token = CvCapabilities::permanent('/cv/jobmesse-26')['token'];

        try {
            $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $token])->assertOk();
            [$privateText, $privateUrls] = $this->inspectPdf($this->get('/cv/jobmesse-26/pdf')->assertOk()->streamedContent());
            $this->assertStringContainsString($email, preg_replace('/\s+/u', '', $privateText));
            $this->assertStringContainsString('#cv='.$token, $privateUrls);

            CvCapabilities::revokePermanent('/cv/jobmesse-26');
            $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $token])->assertNotFound();
            $this->postJson('/cv/private-data', ['path' => '/cv/jobmesse-26'])->assertNotFound();
            [$publicText, $publicUrls] = $this->inspectPdf($this->get('/cv/jobmesse-26/pdf')->assertOk()->streamedContent());
            $this->assertStringNotContainsString($email, $publicText);
            $this->assertStringNotContainsString($token, $publicText.$publicUrls);
        } finally {
            $variables->data($original);
        }
    }

    private function inspectPdf(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'cv-pdf-test-');
        file_put_contents($path, $bytes);
        try {
            $text = new Process(['pdftotext', $path, '-']);
            $text->mustRun();
            $urls = new Process(['pdfinfo', '-url', $path]);
            $urls->mustRun();

            return [$text->getOutput(), $urls->getOutput()];
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_and_wrong_scope_tokens_do_not_authorize(): void
    {
        $token = CvCapabilities::issueTemporary('/cv')['token'];
        $this->postJson('/cv/token-exchange', ['path'=>'/cv/jobmesse-26', 'token'=>$token])->assertNotFound();
        $this->postJson('/cv/token-exchange', ['path'=>'/cv', 'token'=>'invalid'])->assertUnprocessable();
    }

    public function test_uuid_route_is_not_a_share_route(): void
    {
        $this->get('/cv/04d5407d-e18b-49e6-8ac5-9f54146b88cc')->assertNotFound();
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
            $this->get('/cv/jobmesse-26')->assertDontSee('data-cv-explore');
            $global->set('interactive_timeline', true);
            $shown = $this->get('/cv/jobmesse-26')
                ->assertSee('data-cv-explore')
                ->assertSee('Erster RoboCup')
                ->assertSee('Deutsche Meisterschaft mit AtheBlues')
                ->assertDontSee('Grundschule Stade-Hagen');
            $this->assertSame(2, substr_count($shown->getContent(), '<template data-cv-milestone'));
            $global->set('interactive_timeline', false);
            $profile->set('interactive_timeline', 'show');
            $this->get('/cv/jobmesse-26')->assertSee('data-cv-explore');
            $global->set('interactive_timeline', true);
            $profile->set('interactive_timeline', 'hide');
            $this->get('/cv/jobmesse-26')->assertDontSee('data-cv-explore');
        } finally { $global->data($original); $profile->set('interactive_timeline', $previous); }
    }

    public function test_titles_and_about_and_profile_visibility(): void
    {
        $response = $this->get('/cv');
        preg_match('#<title>(.*?)</title>#s', $response->getContent(), $title);
        $this->assertSame('Jack Ruder · Lebenslauf', $title[1]);
        $response->assertSee('Ich entwickle Software');
        $this->get('/cv/jobmesse-26')->assertSee('Jack Ruder · Lebenslauf · Jobmesse 2026');
        $profile = \Statamic\Facades\Entry::find('04d5407d-e18b-49e6-8ac5-9f54146b88cc');
        $original = $profile->get('interactive_timeline');
        try { $profile->set('interactive_timeline', 'hide'); $this->get('/cv/jobmesse-26')->assertDontSee('data-cv-explore'); }
        finally { $profile->set('interactive_timeline', $original); }
    }
}
