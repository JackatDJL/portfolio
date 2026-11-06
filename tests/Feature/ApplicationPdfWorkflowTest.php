<?php

namespace Tests\Feature;

use App\Support\ApplicationCapabilities;
use App\Support\ApplicationData;
use App\Support\ApplicationPdfException;
use App\Support\ApplicationPdfExporter;
use App\Support\ApplicationPdfPostProcessor;
use App\Support\CvCapabilities;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Symfony\Component\Process\Process;
use Tests\Support\ApplicationPdfFixture;
use Tests\TestCase;

class ApplicationPdfWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationPdfFixture $fixture;

    private array $users = [];

    private bool $saved = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic.editions.pro' => true]);
        $this->fixture = new ApplicationPdfFixture;
    }

    protected function tearDown(): void
    {
        if ($this->saved) {
            $this->fixture->entry->delete();
        }
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->fixture->cleanup();
        parent::tearDown();
    }

    private function user(bool $super = true)
    {
        $user = User::make()->id('pdf-test-'.bin2hex(random_bytes(8)))->email(bin2hex(random_bytes(8)).'@example.invalid');
        if ($super) {
            $user->makeSuper();
        }
        $user->save();
        $this->users[] = $user;

        return $user;
    }

    private function save(): string
    {
        $this->fixture->entry->save();
        $this->saved = true;

        return $this->fixture->entry->id();
    }

    private function text(string $pdf): string
    {
        $process = new Process(['pdftotext', '-layout', $pdf, '-']);
        $process->mustRun();

        return $process->getOutput();
    }

    private function inspect(string $pdf): array
    {
        $process = new Process([config('applications.python'), base_path('tests/Support/inspect_application_pdf.py'), $pdf]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_real_letter_composite_order_a4_links_and_private_cv_data(): void
    {
        $this->fixture->documents();
        $pdf = app(ApplicationPdfExporter::class)->render($this->fixture->entry);
        $text = $this->text($pdf);
        foreach (['Beispiel GmbH', 'Musterstraße', '06.11.2026', 'Sehr geehrte', 'Mit freundlichen', 'private-export@example.invalid', '+49 000 123456'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringNotContainsString('Geschützte Angabe', $text);
        $inspection = $this->inspect($pdf);
        $recipientWord = collect($inspection['first_words'])->first(fn ($word) => $word[0] === 'Beispiel');
        $this->assertNotNull($recipientWord);
        $this->assertEqualsWithDelta(25 * 72 / 25.4, $recipientWord[1], 1);
        $this->assertGreaterThan(45 * 72 / 25.4, $recipientWord[2]);
        $this->assertLessThan(90 * 72 / 25.4, $recipientWord[4]);
        foreach ($inspection['first_words'] as $word) {
            $this->assertGreaterThanOrEqual(0, $word[1]);
            $this->assertGreaterThanOrEqual(0, $word[2]);
            $this->assertLessThanOrEqual(595.28, $word[3]);
            $this->assertLessThanOrEqual(841.89, $word[4]);
        }
        $this->assertSame('Anschreiben', $inspection['outline'][0]);
        $this->assertSame('Lebenslauf', $inspection['outline'][1]);
        $this->assertSame(['Erste Anlage', 'Zweite Anlage'], array_slice($inspection['outline'], -2));
        foreach ($inspection['sizes'] as [$width, $height]) {
            $this->assertEqualsWithDelta(595.28, $width, 1);
            $this->assertEqualsWithDelta(841.89, $height, 1);
        }
        $this->assertStringContainsString('Bewerbung', $inspection['texts'][0]);
        $this->assertStringContainsString('Lebenslauf', $inspection['texts'][1]);
        $this->assertStringContainsString('Erste Anlage', $inspection['texts'][count($inspection['texts']) - 4]);
        $this->assertStringContainsString('Zweite Anlage', $inspection['texts'][count($inspection['texts']) - 2]);
        $this->assertContains('https://example.org/application?one=1&two=2#details', $inspection['urls']);
        $this->assertContains('https://example.org/document-0', $inspection['urls']);
        $this->assertContains('https://example.org/document-1', $inspection['urls']);
        $this->assertContains('https://matrix.to/#/%23layout-test:example.invalid', $inspection['urls']);
        foreach ($inspection['urls'] as $url) {
            $this->assertSame(trim($url), $url);
        }
        $this->assertContains(1, $inspection['destinations'][0]); // Cover letter to first CV page.
        $last = count($inspection['texts']) - 1;
        $this->assertContains($last - 2, $inspection['destinations'][$last - 3]);
        $this->assertContains($last, $inspection['destinations'][$last - 1]);
        $this->assertSame(0600, fileperms($pdf) & 0777);
    }

    public function test_long_letter_paginates_on_a4_without_missing_closing(): void
    {
        $letter = ApplicationData::group($this->fixture->entry, 'letter');
        $letter['body'] = array_fill(0, 24, ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Längerer synthetischer Layouttest zur Prüfung des Seitenumbruchs und der vollständigen Übernahme aller Absätze.']]]);
        $this->fixture->entry->set('letter', ApplicationData::encrypt([$letter]));
        $pdf = app(ApplicationPdfExporter::class)->render($this->fixture->entry);
        $inspection = $this->inspect($pdf);
        $this->assertGreaterThan(2, count($inspection['texts']));
        $text = $this->text($pdf);
        $this->assertSame(24, substr_count($text, 'Längerer synthetischer'));
        $this->assertStringContainsString('Mit freundlichen', $text);
    }

    public function test_canonical_cache_is_reused_and_invalidates_on_application_cv_photo_and_document_changes(): void
    {
        $exporter = app(ApplicationPdfExporter::class);
        $this->fixture->documents();
        $first = $exporter->render($this->fixture->entry);
        $this->assertSame($first, $exporter->render($this->fixture->entry));
        $tracking = ApplicationData::group($this->fixture->entry, 'tracking');
        $tracking['date_override'] = '2026-11-07';
        $this->fixture->entry->set('tracking', ApplicationData::encrypt([$tracking]));
        $second = $exporter->render($this->fixture->entry);
        $this->assertNotSame($first, $second);
        $settings = ApplicationData::group($this->fixture->entry, 'cv');
        $profile = Entry::find($settings['profile'][0]);
        $old = $profile->get('accent_color');
        try {
            $profile->set('accent_color', '#1a2540');
            // Remove the application accent so fresh profile resolution is observable.
            unset($settings['accent_color']);
            $this->fixture->entry->set('cv', ApplicationData::encrypt([$settings]));
            $third = $exporter->render($this->fixture->entry);
            $profile->set('accent_color', '#754125');
            $fourth = $exporter->render($this->fixture->entry);
            $this->assertNotSame($third, $fourth);
            file_put_contents($this->fixture->root.'/documents/document-0.pdf', "\n% changed document bytes\n", FILE_APPEND);
            $fifth = $exporter->render($this->fixture->entry);
            $this->assertNotSame($fourth, $fifth);
            file_put_contents($fifth, '%PDF-corrupt');
            $this->assertSame($fifth, $exporter->render($this->fixture->entry));
            $this->assertGreaterThan(1000, filesize($fifth));
        } finally {
            $profile->set('accent_color', $old);
        }
    }

    public function test_photo_bytes_invalidate_the_private_cache_and_are_snapshotted(): void
    {
        $name = 'pdf-test-'.bin2hex(random_bytes(8)).'.png';
        $path = public_path('assets/'.$name);
        $photo = $this->fixture->root.'/photo.png';
        $image = imagecreatetruecolor(12, 12);
        imagepng($image, $photo);
        symlink($photo, $path);
        GlobalSet::find('site')->inDefaultSite()->set('profile_image', $name);
        try {
            $exporter = app(ApplicationPdfExporter::class);
            $first = $exporter->render($this->fixture->entry);
            $this->assertSame($first, $exporter->render($this->fixture->entry));
            imagefill($image, 0, 0, imagecolorallocate($image, 45, 90, 120));
            imagepng($image, $photo);
            $this->assertNotSame($first, $exporter->render($this->fixture->entry));
        } finally {
            unlink($path);
        }
    }

    public function test_concurrent_exports_share_one_atomically_published_canonical_pdf(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl.');
        }
        $children = [];
        for ($index = 0; $index < 2; $index++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Could not fork PDF test.');
            }
            if ($pid === 0) {
                try {
                    file_put_contents($this->fixture->root.'/result-'.$index, app(ApplicationPdfExporter::class)->render($this->fixture->entry));
                    exit(0);
                } catch (\Throwable $error) {
                    file_put_contents($this->fixture->root.'/child-error-'.$index, get_class($error).': '.$error->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, implode(' ', array_map('file_get_contents', glob($this->fixture->root.'/child-error-*'))));
        }
        $this->assertSame(file_get_contents($this->fixture->root.'/result-0'), file_get_contents($this->fixture->root.'/result-1'));
        $this->assertCount(1, glob($this->fixture->root.'/cache/*/*.pdf'));
        $this->assertSame([], glob($this->fixture->root.'/work/*'));
    }

    public function test_failed_post_processing_never_publishes_corrupt_or_partial_output(): void
    {
        $this->app->instance(ApplicationPdfPostProcessor::class, new class implements ApplicationPdfPostProcessor
        {
            public function version(): string
            {
                return 'failure-fixture';
            }

            public function process(string $privatePdf, array $manifest): void
            {
                file_put_contents($privatePdf, '%PDF-broken-post-processing');
            }
        });
        try {
            app(ApplicationPdfExporter::class)->render($this->fixture->entry);
            $this->fail('Invalid post-processing output must not be published.');
        } catch (ApplicationPdfException $exception) {
            $this->assertStringContainsString('Nachbearbeitung', $exception->getMessage());
        }
        $this->assertSame([], glob($this->fixture->root.'/cache/*/*.pdf'));
        $this->assertSame([], glob($this->fixture->root.'/work/*'));
    }

    public function test_exchange_requires_csrf_outside_the_testing_bypass(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $request = Request::create('/applications/token-exchange', 'POST');
        $session = $this->app['session']->driver();
        $session->regenerateToken();
        $request->setLaravelSession($session);
        try {
            $middleware->handle($request, fn () => response('ok'));
            $this->fail('An exchange without CSRF must fail.');
        } catch (TokenMismatchException) {
            $this->assertTrue(true);
        }
        $request->headers->set('X-CSRF-TOKEN', $session->token());
        $this->assertSame(200, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
    }

    public function test_expired_or_other_application_grants_cannot_authorize_and_only_hashes_enter_the_session(): void
    {
        $id = $this->save();
        $token = ApplicationCapabilities::issue($id);
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token])->assertOk();
        $this->assertSame(hash('sha256', $token), session('application_grants.'.hash('sha256', $id)));
        $this->assertFalse(ApplicationCapabilities::allowsHash((string) Str::uuid(), hash('sha256', $token)));
        $this->assertNull(session('cv_grants'));
        DB::table('application_access_tokens')->where('application_id', $id)->update(['expires_at' => now()->subMinute()]);
        $this->get('/applications/'.$id.'/pdf')->assertNotFound();
    }

    public function test_preview_is_ephemeral_and_cp_permissions_are_enforced(): void
    {
        $id = $this->save();
        $this->getJson('/cp/applications/'.$id.'/preview.pdf')->assertUnauthorized();
        $this->actingAs($this->user(false));
        $this->getJson('/cp/applications/'.$id.'/preview.pdf')->assertForbidden();
        $this->postJson('/cp/applications/'.$id.'/recipient-link')->assertForbidden();
        $this->actingAs($this->user());
        $preview = $this->get('/cp/applications/'.$id.'/preview.pdf')->assertOk();
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->assertStringContainsString('preview-', $preview->baseResponse->getFile()->getPathname());
        $export = $this->get('/cp/applications/'.$id.'/export.pdf')->assertOk();
        $this->assertStringContainsString('attachment', $export->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private-export@example.invalid', $this->text($export->baseResponse->getFile()->getPathname()));
    }

    public function test_application_grants_are_scoped_revocable_rotatable_and_independent_of_cv_grants(): void
    {
        $id = $this->save();
        $cvGrant = CvCapabilities::issueTemporary('/cv');
        $this->postJson('/cv/token-exchange', ['path' => '/cv', 'token' => $cvGrant['token']])->assertOk();
        $this->get('/applications/'.$id.'/pdf')->assertNotFound();
        $this->get('/applications/'.$id)->assertOk()->assertDontSee('Beispiel GmbH')->assertDontSee('private-export');
        $editor = $this->user();
        $this->actingAs($editor);
        $issue = $this->postJson('/cp/applications/'.$id.'/recipient-link')->assertOk();
        $token = explode('#application=', $issue->json('url'))[1];
        $this->app['auth']->logout();
        $row = (array) DB::table('application_access_tokens')->where('application_id', $id)->first();
        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token], ['Origin' => 'https://evil.invalid'])->assertForbidden();
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $cvGrant['token']])->assertNotFound();
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token])->assertOk();
        $this->get('/applications/'.$id.'/pdf?token='.$token)->assertNotFound();
        $pdf = $this->get('/applications/'.$id.'/pdf?public=true')->assertOk();
        $this->assertStringContainsString('private-export@example.invalid', $this->text($pdf->baseResponse->getFile()->getPathname()));
        $newToken = ApplicationCapabilities::issue($id);
        $this->get('/applications/'.$id.'/pdf')->assertNotFound();
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token])->assertNotFound();
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $newToken])->assertOk();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs($editor);
        $this->deleteJson('/cp/applications/'.$id.'/recipient-link')->assertOk();
        $this->get('/applications/'.$id.'/pdf')->assertNotFound();
    }

    public function test_revocation_during_generation_stops_the_pending_recipient_response(): void
    {
        $id = $this->save();
        $token = ApplicationCapabilities::issue($id);
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token])->assertOk();
        $this->app->instance(ApplicationPdfPostProcessor::class, new class($id) implements ApplicationPdfPostProcessor
        {
            public function __construct(private string $id) {}

            public function version(): string
            {
                return 'revoke-during-generation';
            }

            public function process(string $privatePdf, array $manifest): void
            {
                ApplicationCapabilities::revoke($this->id);
            }
        });
        $this->get('/applications/'.$id.'/pdf')->assertNotFound()->assertDontSee('private-export@example.invalid');
    }

    public function test_exchange_has_a_separate_rate_limit(): void
    {
        $id = $this->save();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => str_repeat('x', 64)])->assertNotFound();
        }
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => str_repeat('x', 64)])->assertStatus(429);
        $this->get('/applications/'.$id.'/pdf')->assertNotFound();
    }

    public function test_missing_export_fields_return_actionable_cp_errors_without_recipient_details(): void
    {
        $letter = ApplicationData::group($this->fixture->entry, 'letter');
        $letter['subject'] = '';
        $this->fixture->entry->set('letter', ApplicationData::encrypt([$letter]));
        $id = $this->save();
        $this->actingAs($this->user());
        $this->get('/cp/applications/'.$id.'/preview.pdf')->assertStatus(422)->assertSee('Betreff');
        $token = ApplicationCapabilities::issue($id);
        $this->app['auth']->logout();
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $token])->assertOk();
        $this->get('/applications/'.$id.'/pdf')->assertStatus(503)->assertDontSee('private-export@example.invalid')->assertDontSee('Beispiel GmbH');
    }

    public function test_invalid_private_ciphertext_or_bad_documents_never_publish_a_partial_export(): void
    {
        $global = GlobalSet::find('cv')->inDefaultSite();
        $original = $global->get('private_email');
        $global->set('private_email', 'INVALID-PRIVATE-CIPHERTEXT');
        try {
            app(ApplicationPdfExporter::class)->render($this->fixture->entry);
            $this->fail('Corrupt private data must fail.');
        } catch (ApplicationPdfException $exception) {
            $this->assertStringNotContainsString('INVALID-PRIVATE', $exception->getMessage());
        } finally {
            $global->set('private_email', $original);
        }
        $this->assertSame([], glob($this->fixture->root.'/work/*'));
        $this->assertSame([], glob($this->fixture->root.'/cache/*/*.pdf'));
        file_put_contents($this->fixture->root.'/documents/broken.pdf', '%PDF-invalid');
        $this->fixture->entry->set('documents', ApplicationData::encrypt([['mode' => 'override', 'items' => [['file' => ['broken.pdf'], 'label' => 'Private invalid document']]]]));
        try {
            app(ApplicationPdfExporter::class)->render($this->fixture->entry);
            $this->fail('Broken PDF must fail.');
        } catch (ApplicationPdfException) {
            $this->assertSame([], glob($this->fixture->root.'/cache/*/*.pdf'));
        }
        $this->assertSame([], glob($this->fixture->root.'/work/*'));
    }

    public function test_latex_injection_is_escaped_and_compile_failure_is_redacted(): void
    {
        $letter = ApplicationData::group($this->fixture->entry, 'letter');
        $letter['subject'] = '\\input{/etc/passwd} & # % _ { }';
        $this->fixture->entry->set('letter', ApplicationData::encrypt([$letter]));
        $pdf = app(ApplicationPdfExporter::class)->render($this->fixture->entry);
        $text = $this->text($pdf);
        $this->assertStringContainsString('input', $text);
        $this->assertStringNotContainsString('root:x:', $text);
        config(['cv.lualatex' => '/does-not-exist/private-compiler']);
        $this->expectException(ApplicationPdfException::class);
        app(ApplicationPdfExporter::class)->render($this->fixture->entry, cached: false);
    }
}
