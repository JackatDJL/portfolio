<?php

namespace Tests\Feature;

use App\Support\ApplicationData;
use App\Support\ApplicationPdfExporter;
use App\Support\CvViewModel;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Symfony\Component\Process\Process;
use Tests\Support\ApplicationPdfFixture;
use Tests\Support\HandwrittenSignatureFixture;
use Tests\TestCase;

class ApplicationHandwrittenSignatureTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationPdfFixture $fixture;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic.editions.pro' => true]);
        $this->fixture = new ApplicationPdfFixture;
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->fixture->cleanup();
        parent::tearDown();
    }

    private function saveSource(): PrivateHandwrittenSignature
    {
        $png = HandwrittenSignatureFixture::png();
        $signature = app(PrivateHandwrittenSignature::class);
        $signature->save('data:image/png;base64,'.base64_encode($png), HandwrittenSignatureFixture::strokes());

        return $signature;
    }

    private function user(bool $super = true)
    {
        $user = User::make()->id('handwriting-test-'.bin2hex(random_bytes(8)))->email(bin2hex(random_bytes(8)).'@example.invalid');
        if ($super) {
            $user->makeSuper();
        }
        $user->save();
        $this->users[] = $user;

        return $user;
    }

    public function test_signature_source_is_encrypted_and_materialized_with_private_permissions(): void
    {
        $png = HandwrittenSignatureFixture::png();
        $signature = app(PrivateHandwrittenSignature::class);
        $signature->save('data:image/png;base64,'.base64_encode($png), HandwrittenSignatureFixture::strokes());
        $path = config('applications.handwritten_signature_path').'/source.enc';
        $encrypted = file_get_contents($path);

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString(base64_encode($png), $encrypted);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame(0700, fileperms(dirname($path)) & 0777);
        $this->assertSame('data:image/png;base64,'.base64_encode($png), $signature->editorData()['image']);
        $this->assertSame(HandwrittenSignatureFixture::strokes(), $signature->editorData()['strokes']);

        $materialized = $signature->materialize($this->fixture->root.'/work-signature.png');
        $this->assertSame(hash('sha256', $png), $materialized['sha256']);
        $this->assertSame($png, file_get_contents($materialized['path']));
        $this->assertSame(0600, fileperms($materialized['path']) & 0777);
    }

    public function test_private_signature_cp_endpoints_require_superuser_and_keep_responses_private(): void
    {
        $this->getJson('/cp/applications/handwritten-signature')->assertUnauthorized();
        $this->actingAs($this->user(false))
            ->getJson('/cp/applications/handwritten-signature')->assertForbidden();

        $this->actingAs($this->user());
        $this->getJson('/cp/applications/handwritten-signature')->assertOk()
            ->assertJsonPath('available', false)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $png = HandwrittenSignatureFixture::png();
        $this->postJson('/cp/applications/handwritten-signature', [
            'image' => 'data:image/png;base64,'.base64_encode($png),
            'strokes' => HandwrittenSignatureFixture::strokes(),
        ])->assertOk()->assertJsonPath('saved', true);
        $this->getJson('/cp/applications/handwritten-signature')->assertJsonPath('available', true)
            ->assertJsonPath('source.image', 'data:image/png;base64,'.base64_encode($png));
    }

    public function test_signature_source_cannot_be_deleted_while_an_application_uses_it(): void
    {
        $this->saveSource();
        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 0]]));
        $this->fixture->entry->save();
        $this->actingAs($this->user());
        $this->deleteJson('/cp/applications/handwritten-signature')->assertStatus(409);

        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'none']]));
        $this->fixture->entry->save();
        $this->deleteJson('/cp/applications/handwritten-signature')->assertOk()->assertJsonPath('deleted', true);
    }

    public function test_application_signature_validation_requires_a_saved_private_source(): void
    {
        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 0]]));
        $this->expectException(ValidationException::class);
        $this->fixture->entry->save();
    }

    public function test_public_cv_does_not_augment_or_render_legacy_signature_data(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $old = $variables->value('signature');
        try {
            $variables->set('signature', ['cv/signatures/private-sentinel.png']);
            $this->assertArrayNotHasKey('signature', app(CvViewModel::class)->make());
            $this->get('/cv')->assertOk()->assertDontSee('cv/signatures/private-sentinel.png');
        } finally {
            $variables->set('signature', $old);
        }
    }

    public function test_authorized_composite_renders_handwriting_on_letter_and_cv_without_extra_pages(): void
    {
        $signature = $this->saveSource();
        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'none']]));
        $exporter = app(ApplicationPdfExporter::class);
        $unsigned = $exporter->render($this->fixture->entry, cached: false);
        $unsignedInfo = json_decode((new Process([config('applications.python'), base_path('tests/Support/inspect_application_pdf.py'), $unsigned]))->mustRun()->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 1]]));
        $signedVisually = $exporter->render($this->fixture->entry, cached: false);
        $signedInfo = json_decode((new Process([config('applications.python'), base_path('tests/Support/inspect_application_pdf.py'), $signedVisually]))->mustRun()->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $text = (new Process(['pdftotext', '-layout', $signedVisually, '-']))->mustRun()->getOutput();
        $images = (new Process(['pdfimages', '-list', $signedVisually]))->mustRun()->getOutput();

        $this->assertSame($unsignedInfo['sizes'], $signedInfo['sizes']);
        $this->assertStringContainsString('Musterstadt, 6. November 2026', $text);
        $this->assertMatchesRegularExpression('/^\s*1\s+\d+\s+image\s+480\s+140\b/m', $images);
        $this->assertMatchesRegularExpression('/^\s*[2-9]\d*\s+\d+\s+image\s+480\s+140\b/m', $images);
        $this->assertStringNotContainsString(base64_encode(HandwrittenSignatureFixture::png()), $text);
        $this->assertSame(hash('sha256', HandwrittenSignatureFixture::png()), $signature->materialize($this->fixture->root.'/digest-check.png')['sha256']);
    }

    public function test_changing_handwriting_invalidates_the_canonical_pdf_cache(): void
    {
        $png = HandwrittenSignatureFixture::png();
        $signature = app(PrivateHandwrittenSignature::class);
        $signature->save('data:image/png;base64,'.base64_encode($png), HandwrittenSignatureFixture::strokes());
        $this->fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 0]]));
        $exporter = app(ApplicationPdfExporter::class);
        $first = $exporter->render($this->fixture->entry);
        $changed = HandwrittenSignatureFixture::png(variant: 1);
        $signature->save('data:image/png;base64,'.base64_encode($changed), HandwrittenSignatureFixture::strokes());
        $second = $exporter->render($this->fixture->entry);

        $this->assertNotSame($first, $second);
    }
}
