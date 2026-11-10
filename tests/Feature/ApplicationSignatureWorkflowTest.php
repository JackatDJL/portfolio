<?php

namespace Tests\Feature;

use App\Signatures\ApplicationCompositeSource;
use App\Signatures\ApplicationSignatureWorkflow;
use App\Signatures\LocalPdfSignatureValidator;
use App\Signatures\SandboxSignatureProvider;
use App\Signatures\SignatureWorkflowException;
use App\Support\ApplicationPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Symfony\Component\Process\Process;
use Tests\Support\ApplicationPdfFixture;
use Tests\TestCase;

class ApplicationSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $pdf;

    private $entry;

    private ApplicationSignatureWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/signature-tests-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->pdf = $this->directory.'/source.pdf';
        config(['signatures.root' => $this->directory.'/archive', 'signatures.sandbox_enabled' => true, 'applications.python' => env('APPLICATION_PDF_PYTHON', 'python3')]);
        $process = new Process([config('applications.python'), '-c', 'from pypdf import PdfWriter; from pypdf.generic import *; import sys; w=PdfWriter(); w.add_blank_page(595.276,841.89); w._root_object[NameObject("/AcroForm")]=w._add_object(DictionaryObject({NameObject("/Fields"):ArrayObject(),NameObject("/SigFlags"):NumberObject(3)})); w.write(sys.argv[1])', $this->pdf]);
        $process->mustRun();
        $this->entry = Entry::make()->id('899e2c93-765d-42dd-86d2-dc3735c910d8')->collection('applications');
        $source = new class($this->pdf) implements ApplicationCompositeSource
        {
            public function __construct(private string $path) {}

            public function render(\Statamic\Contracts\Entries\Entry $entry, bool $cached = true): string
            {
                return $this->path;
            }
        };
        $this->workflow = new ApplicationSignatureWorkflow($source, new LocalPdfSignatureValidator, new SandboxSignatureProvider);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_explicit_sandbox_import_keeps_original_and_never_claims_qes(): void
    {
        $v = $this->workflow->prepare($this->entry, 'owner');
        $original = file_get_contents($this->workflow->path($v));
        $this->assertSame('prepared', $v->state);
        $this->assertNull($v->signed_sha256);
        $this->workflow->sandbox($v, $v->unsigned_sha256);
        $v->refresh();
        $this->assertSame($original, file_get_contents($this->workflow->path($v)));
        $this->assertFalse($v->verification['qes_verified']);
        $this->assertSame('untrusted', $v->verification['certificate_path']);
        $this->assertSame('not_checked_offline', $v->verification['revocation']);
        $this->assertStringNotContainsString('Sandbox', DB::table('application_signature_versions')->value('verification'));
        $this->workflow->select($this->entry->id(), $v, 'owner');
        $this->assertSame($this->workflow->path($v, true), $this->workflow->delivery($this->entry, $this->pdf, false));
        $this->expectException(SignatureWorkflowException::class);
        $this->workflow->delivery($this->entry, $this->pdf, true);
    }

    public function test_external_import_and_content_change_fail_closed_without_unsigned_fallback(): void
    {
        $v = $this->workflow->prepare($this->entry, 'owner');
        $signed = $this->directory.'/external.pdf';
        (new SandboxSignatureProvider)->sign($this->workflow->path($v), $v->unsigned_sha256, $signed);
        $this->workflow->import($v, $signed, $v->unsigned_sha256);
        $this->workflow->select($this->entry->id(), $v, 'owner');
        $this->assertSame($this->workflow->path($v, true), $this->workflow->delivery($this->entry, $this->pdf, true));
        file_put_contents($this->pdf, "\nchanged", FILE_APPEND);
        try {
            $this->workflow->delivery($this->entry, $this->pdf, true);
            $this->fail('Stale signature delivered');
        } catch (SignatureWorkflowException) {
        }
        $this->workflow->select($this->entry->id(), null, 'owner');
        $this->assertSame($this->pdf, $this->workflow->delivery($this->entry, $this->pdf, true));
    }

    public function test_wrong_digest_does_not_authorize_signing(): void
    {
        $v = $this->workflow->prepare($this->entry, 'owner');
        $this->expectException(SignatureWorkflowException::class);
        $this->workflow->sandbox($v, str_repeat('0', 64));
    }

    public function test_production_cannot_use_sandbox_even_when_enabled(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->assertFalse((new SandboxSignatureProvider)->capabilities()->available);
    }

    public function test_modified_pages_in_incremental_update_are_rejected(): void
    {
        $v = $this->workflow->prepare($this->entry, 'owner');
        $changed = $this->directory.'/changed.pdf';
        $process = new Process([config('applications.python'), '-c', 'from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter; from pyhanko.pdf_utils.generic import *; import sys; f=open(sys.argv[1],"rb"); w=IncrementalPdfFileWriter(f); page=w.root["/Pages"]["/Kids"][0].get_object(); page[NameObject("/Rotate")]=NumberObject(90); w.mark_update(w.root["/Pages"]["/Kids"].raw_get(0)); w.write(open(sys.argv[2],"wb"))', $this->pdf, $changed]);
        $process->mustRun();
        $signed = $this->directory.'/malicious.pdf';
        (new SandboxSignatureProvider)->sign($changed, hash_file('sha256', $changed), $signed);
        $this->expectException(SignatureWorkflowException::class);
        $this->workflow->import($v, $signed, $v->unsigned_sha256);
    }

    public function test_real_composite_can_be_signed_without_changing_pages_or_links(): void
    {
        $fixture = new ApplicationPdfFixture;
        try {
            $fixture->documents();
            $exporter = app(ApplicationPdfExporter::class);
            $workflow = new ApplicationSignatureWorkflow($exporter, new LocalPdfSignatureValidator, new SandboxSignatureProvider);
            $v = $workflow->prepare($fixture->entry, 'owner');
            $unsigned = $workflow->path($v);
            $signed = $this->directory.'/complete-signed.pdf';
            (new SandboxSignatureProvider)->sign($unsigned, $v->unsigned_sha256, $signed);
            $workflow->import($v, $signed, $v->unsigned_sha256);
            $inspect = fn ($path) => json_decode((new Process([config('applications.python'), base_path('tests/Support/inspect_application_pdf.py'), $path]))->mustRun()->getOutput(), true);
            $before = $inspect($unsigned);
            $after = $inspect($workflow->path($v, true));
            foreach (['sizes', 'texts', 'urls', 'destinations', 'outline'] as $key) {
                $this->assertSame($before[$key], $after[$key]);
            }
            $this->assertStringContainsString('private-export@example.invalid', implode(' ', $after['texts']));
            $this->assertCount(7, $after['sizes']);
        } finally {
            $fixture->cleanup();
        }
    }

    public function test_failed_import_can_retry_but_validated_artifact_cannot_be_overwritten(): void
    {
        $v = $this->workflow->prepare($this->entry, 'owner');
        try {
            $this->workflow->import($v, $this->pdf, $v->unsigned_sha256);
            $this->fail('Unsigned import accepted');
        } catch (SignatureWorkflowException) {
        }
        $this->assertSame('prepared', $v->fresh()->state);
        $signed = $this->directory.'/retry.pdf';
        (new SandboxSignatureProvider)->sign($this->workflow->path($v), $v->unsigned_sha256, $signed);
        $this->workflow->import($v, $signed, $v->unsigned_sha256);
        $digest = hash_file('sha256', $this->workflow->path($v, true));
        try {
            $this->workflow->import($v, $signed, $v->unsigned_sha256);
            $this->fail('Overwrite accepted');
        } catch (SignatureWorkflowException) {
        }
        $this->assertSame($digest, hash_file('sha256', $this->workflow->path($v, true)));
        file_put_contents($this->workflow->path($v, true), 'tampered');
        $this->expectException(SignatureWorkflowException::class);
        $this->workflow->path($v, true);
    }

    public function test_cp_status_and_archive_require_super_and_mutations_require_explicit_approval(): void
    {
        config(['statamic.editions.pro' => true]);
        Entry::shouldReceive('find')->with($this->entry->id())->andReturn($this->entry);
        $this->app->instance(ApplicationSignatureWorkflow::class, $this->workflow);
        $user = User::make()->id('signature-test-owner')->email('signature-owner@example.invalid')->makeSuper();
        $user->save();
        try {
            $this->actingAs($user);
            $this->getJson('/cp/applications/'.$this->entry->id().'/signatures/status')->assertOk()->assertJsonPath('mode', 'unsigned')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $v = $this->workflow->prepare($this->entry, $user->id());
            $this->postJson('/cp/applications/'.$this->entry->id().'/signatures/sandbox', ['version' => $v->id, 'sha256' => $v->unsigned_sha256])->assertUnprocessable();
            $this->assertSame('prepared', $v->fresh()->state);
            $this->get('/cp/applications/'.$this->entry->id().'/signatures/download?version='.$v->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $signed = $this->directory.'/cp-import.pdf';
            (new SandboxSignatureProvider)->sign($this->workflow->path($v), $v->unsigned_sha256, $signed);
            $this->post('/cp/applications/'.$this->entry->id().'/signatures/import', ['authorized' => '1', 'version' => $v->id, 'sha256' => $v->unsigned_sha256, 'pdf' => new UploadedFile($signed, 'signed.pdf', 'application/pdf', null, true)], ['Accept' => 'application/json'])->assertOk();
            $this->getJson('/cp/applications/'.$this->entry->id().'/signatures/status')->assertJsonPath('mode', 'unsigned')->assertJsonPath('versions.0.verification.qes_verified', false);
            $this->postJson('/cp/applications/'.$this->entry->id().'/signatures/activate', ['authorized' => true, 'version' => $v->id])->assertOk();
            $this->getJson('/cp/applications/'.$this->entry->id().'/signatures/status')->assertJsonPath('mode', 'external');

        } finally {
            $user->delete();
        }
    }

    public function test_unauthorized_cp_user_cannot_read_signature_status(): void
    {
        $this->get('/cp/applications/'.$this->entry->id().'/signatures/status')->assertRedirect();
        $user = User::make()->id('signature-test-user')->email('signature@example.invalid');
        $user->save();
        try {
            $this->actingAs($user)->getJson('/cp/applications/'.$this->entry->id().'/signatures/status')->assertForbidden();
        } finally {
            $user->delete();
        }
    }
}
