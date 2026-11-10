<?php

namespace Tests\Feature;

use App\Support\ApplicationData;
use App\Support\CvPdfRenderer;
use App\Support\CvPrivateData;
use App\Support\CvProfileContentResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Tests\TestCase;

class ApplicationManagementTest extends TestCase
{
    private array $entries = [];

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic.editions.pro' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->entries as $entry) {
            $entry->delete();
        }
        foreach ($this->users as $user) {
            $user->delete();
        }
        parent::tearDown();
    }

    private function user(bool $super = true)
    {
        $user = User::make()->id('application-test-'.bin2hex(random_bytes(8)))->email(bin2hex(random_bytes(8)).'@example.invalid');
        if ($super) {
            $user->makeSuper();
        }
        $user->save();
        $this->users[] = $user;

        return $user;
    }

    private function values(): array
    {
        return [
            'title' => 'Test reference', 'application_status' => 'draft', 'published' => true,
            'recipient' => [['organisation' => 'PRIVATE-ORG-SENTINEL', 'position' => 'Internship', 'email' => 'private@example.invalid']],
            'letter' => [['body' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'PRIVATE-LETTER-SENTINEL']]]]]],
            'cv' => [[]], 'documents' => [['mode' => 'override', 'items' => []]],
            'signature' => [['mode' => 'none']],
            'tracking' => [['application_date' => '2026-01-01', 'date_override' => '2026-02-01', 'notes' => 'PRIVATE-NOTE-SENTINEL']],
        ];
    }

    private function createThroughCp()
    {
        $this->actingAs($this->user());
        $this->getJson('/cp/collections/applications/entries/create/'.Site::default()->handle())->assertOk();
        $response = $this->postJson('/cp/collections/applications/entries/'.Site::default()->handle(), $this->values())->assertOk();
        $entry = Entry::find($response->json('data.id'));
        $this->assertNotNull($entry);
        $this->entries[] = $entry;

        return $entry;
    }

    public function test_cp_round_trip_encrypts_sensitive_fields_and_forces_private_entry(): void
    {
        $entry = $this->createThroughCp();
        $this->assertFalse($entry->published());
        $this->assertNull($entry->url());
        $source = file_get_contents($entry->path());
        foreach (['PRIVATE-ORG-SENTINEL', 'PRIVATE-LETTER-SENTINEL', 'PRIVATE-NOTE-SENTINEL'] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $source);
            foreach (['/llms.txt', '/llms-full.txt', '/sitemap.xml', '/cv'] as $url) {
                $this->get($url)->assertDontSee($sentinel);
            }
        }
        $this->assertSame('PRIVATE-ORG-SENTINEL', ApplicationData::group($entry, 'recipient')['organisation']);
        $fields = $entry->blueprint()->fields()->addValues($entry->data()->all())->preProcess()->values()->all();
        $this->assertSame('PRIVATE-ORG-SENTINEL', $fields['recipient'][0]['organisation']);
        $this->assertNull($entry->toAugmentedArray(['recipient'])['recipient']->value());
        $this->get('/applications/'.$entry->slug())->assertNotFound();
        $this->get('/cp/collections/applications/entries/'.$entry->id())->assertOk();
    }

    public function test_date_override_and_reset_preserve_private_notes(): void
    {
        $entry = $this->createThroughCp();
        $this->assertSame('2026-02-01', ApplicationData::date($entry));
        $this->postJson('/cp/applications/'.$entry->id().'/reset-date')->assertOk()->assertJson(['date' => now()->toDateString()]);
        $entry = Entry::find($entry->id());
        $this->assertSame(now()->toDateString(), ApplicationData::date($entry));
        $this->assertSame('PRIVATE-NOTE-SENTINEL', ApplicationData::group($entry, 'tracking')['notes']);
    }

    public function test_guests_and_non_super_users_cannot_manage_applications_or_documents(): void
    {
        $this->postJson('/cp/applications/missing/reset-date')->assertUnauthorized();
        $user = $this->user(false);
        $this->actingAs($user);
        $this->assertFalse(Gate::forUser($user)->allows('store', [\Statamic\Contracts\Entries\Entry::class, Collection::find('applications')]));
        $this->assertFalse(Gate::forUser($user)->allows('view', AssetContainer::find('application_documents')));
        $this->assertFalse(Gate::forUser($user)->allows('edit', GlobalSet::find('application_defaults')));
        $this->postJson('/cp/applications/missing/reset-date')->assertForbidden();
        $this->getJson('/cp/applications/missing/cv.pdf')->assertForbidden();
    }

    public function test_validation_rejects_invalid_recipient(): void
    {
        $this->actingAs($this->user());
        $values = $this->values();
        $values['recipient'][0]['email'] = 'not-an-email';
        $this->postJson('/cp/collections/applications/entries/'.Site::default()->handle(), $values)->assertUnprocessable();
    }

    public function test_typed_signature_requires_a_name(): void
    {
        $this->actingAs($this->user());
        $values = $this->values();
        $values['signature'] = [['mode' => 'typed', 'name' => '']];
        $this->postJson('/cp/collections/applications/entries/'.Site::default()->handle(), $values)->assertUnprocessable();
    }

    public function test_private_document_disk_has_no_public_url_or_serving(): void
    {
        $container = AssetContainer::find('application_documents');
        $this->assertTrue($container->private());
        $this->assertNull($container->makeAsset('test.pdf')->url());
        $this->assertFalse(config('filesystems.disks.application_documents.serve'));
        $this->assertStringStartsWith(storage_path('app/private/'), config('filesystems.disks.application_documents.root'));
    }

    public function test_attachment_defaults_preserve_order_and_allow_explicit_empty_override(): void
    {
        $defaults = GlobalSet::find('application_defaults')->inDefaultSite();
        $original = $defaults->data()->all();
        $hadFile = is_file($defaults->path());
        $items = [['label' => 'Second', 'file' => ['second.pdf']], ['label' => 'First', 'file' => ['first.pdf']]];
        $defaults->set('documents', ApplicationData::encrypt([['items' => $items]]));
        $defaults->save();
        try {
            $entry = Entry::make()->collection('applications')->data(['documents' => ApplicationData::encrypt([['mode' => 'inherit']])]);
            $this->assertSame($items, ApplicationData::documents($entry));
            $entry->set('documents', ApplicationData::encrypt([['mode' => 'override', 'items' => []]]));
            $this->assertSame([], ApplicationData::documents($entry));
        } finally {
            $defaults->data($original);
            if ($hadFile) {
                $defaults->save();
            } else {
                $defaults->delete();
            }
        }
    }

    public function test_reusable_text_components_are_resolved_from_encrypted_defaults(): void
    {
        $defaults = GlobalSet::find('application_defaults')->inDefaultSite();
        $original = $defaults->data()->all();
        $hadFile = is_file($defaults->path());
        $body = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'PRIVATE-COMPONENT-SENTINEL']]]];
        $defaults->set('text_components', ApplicationData::encrypt([['items' => [['key' => 'test', 'label' => 'Test', 'body' => $body]]]]));
        $defaults->save();
        try {
            $entry = Entry::make()->collection('applications')->data(['letter' => ApplicationData::encrypt([['body' => [['type' => 'set', 'attrs' => ['values' => ['type' => 'component', 'component' => 'test']]]]]])]);
            $this->assertSame($body, ApplicationData::coverLetter($entry));
        } finally {
            $defaults->data($original);
            if ($hadFile) {
                $defaults->save();
            } else {
                $defaults->delete();
            }
        }
    }

    public function test_document_upload_rejects_non_pdf_files(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/assets', ['container' => 'application_documents', 'folder' => '/', 'file' => UploadedFile::fake()->create('unsafe.html', 1, 'text/html')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_pdf_upload_and_authenticated_download_remain_private(): void
    {
        $this->actingAs($this->user());
        $response = $this->post('/cp/assets', ['container' => 'application_documents', 'folder' => '/', 'file' => UploadedFile::fake()->createWithContent('application-test-'.bin2hex(random_bytes(8)).'.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")], ['Accept' => 'application/json'])->assertOk();
        $asset = Asset::find($response->json('data.id'));
        $this->assertNotNull($asset);
        try {
            $url = '/cp/assets/'.base64_encode($asset->id()).'/download';
            $download = $this->get($url)->assertOk();
            $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
            $this->actingAs($this->user(false));
            $this->getJson($url)->assertForbidden();
        } finally {
            $asset->deleteQuietly();
        }
    }

    public function test_application_cv_compiles_through_existing_private_pipeline(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = [];
        foreach (CvPrivateData::FIELDS as $field) {
            $original[$field] = $variables->value($field);
            $variables->set($field, '');
        }

        $entry = Entry::make()->collection('applications')->data([
            'cv' => ApplicationData::encrypt([['introduction' => 'Application specific introduction', 'accent_color' => '#123456']]),
        ]);

        try {
            $file = app(CvPdfRenderer::class)->renderApplication($entry);
            $this->assertStringStartsWith(storage_path('app/private/'), $file);
            $this->assertStringStartsWith('%PDF-', file_get_contents($file));
            $this->assertSame(0600, fileperms($file) & 0777);
        } finally {
            if (isset($file)) {
                @unlink($file);
            }
            foreach ($original as $field => $value) {
                $variables->set($field, $value);
            }
        }
    }

    public function test_application_cv_export_fails_closed_on_corrupt_private_cv_data(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = $variables->value('private_email');
        $variables->set('private_email', 'configured-but-not-encrypted');

        try {
            $entry = Entry::make()->collection('applications')->data([
                'cv' => ApplicationData::encrypt([[]]),
            ]);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Configured private CV data cannot be decrypted.');
            app(CvPdfRenderer::class)->renderApplication($entry);
        } finally {
            $variables->set('private_email', $original);
        }
    }

    public function test_global_defaults_are_encrypted_through_actual_cp_save(): void
    {
        $this->actingAs($this->user());
        $defaults = GlobalSet::find('application_defaults')->inDefaultSite();
        $original = $defaults->data()->all();
        $hadFile = is_file($defaults->path());
        try {
            $this->getJson('/cp/globals/application_defaults')->assertOk();
            $this->patchJson('/cp/globals/application_defaults/variables', [
                'documents' => [['items' => []]],
                'text_components' => [['items' => [['key' => 'test', 'label' => 'PRIVATE-DEFAULT-SENTINEL', 'body' => []]]]],
            ])->assertOk();
            $this->assertSame('PRIVATE-DEFAULT-SENTINEL', ApplicationData::textComponents()[0]['label']);
            $this->assertStringNotContainsString('PRIVATE-DEFAULT-SENTINEL', file_get_contents($defaults->path()));
        } finally {
            $defaults = GlobalSet::find('application_defaults')->inDefaultSite();
            $defaults->data($original);
            if ($hadFile) {
                $defaults->save();
            } else {
                $defaults->delete();
            }
        }
    }

    public function test_cp_update_preserves_encrypted_fields_and_validates_period(): void
    {
        $entry = $this->createThroughCp();
        $values = $this->values();
        $values['recipient'][0]['period_start'] = '2026-02-01';
        $values['recipient'][0]['period_end'] = '2026-01-01';
        $url = '/cp/collections/applications/entries/'.$entry->id();
        $this->patchJson($url, $values)->assertUnprocessable();
        $values['recipient'][0]['period_end'] = '2026-03-01';
        $values['recipient'][0]['organisation'] = 'UPDATED-PRIVATE-SENTINEL';
        $this->patchJson($url, $values)->assertOk();
        $this->assertSame('UPDATED-PRIVATE-SENTINEL', ApplicationData::group(Entry::find($entry->id()), 'recipient')['organisation']);
        $this->assertStringNotContainsString('UPDATED-PRIVATE-SENTINEL', file_get_contents($entry->path()));
    }

    public function test_application_cv_overrides_do_not_mutate_profile_or_globals(): void
    {
        $entry = Entry::make()->collection('applications')->data([
            'cv' => ApplicationData::encrypt([['introduction' => 'PRIVATE-INTRO-SENTINEL', 'projects_source' => 'hide', 'accent_color' => '#123456']]),
        ]);
        $resolver = app(CvProfileContentResolver::class);
        $before = $resolver->resolve();
        $resolved = $resolver->resolveForApplication($entry);
        $this->assertSame('PRIVATE-INTRO-SENTINEL', $resolved['sections']['about']['value']);
        $this->assertSame([], $resolved['sections']['projects']['value']);
        $this->assertSame('#123456', $resolved['application_accent']);
        $this->assertEquals($before, $resolver->resolve());
    }

    public function test_application_cv_suppresses_source_profile_title_and_respects_hidden_about_section(): void
    {
        $profile = Entry::make()->collection('cv_profiles')->slug('application-title-'.bin2hex(random_bytes(5)))->published(true)->data([
            'title' => 'Lufthansa Technik Praktikum 2027',
            'source_mode' => 'custom',
        ]);
        $profile->save();

        try {
            $entry = Entry::make()->collection('applications')->data([
                'cv' => ApplicationData::encrypt([[
                    'profile' => [$profile->id()],
                    'about_source' => 'hide',
                    'introduction' => 'PRIVATE-INTRO-MUST-STAY-HIDDEN',
                ]]),
            ]);

            $resolved = app(CvProfileContentResolver::class)->resolveForApplication($entry);

            $this->assertSame('Lebenslauf zur Bewerbung', $resolved['profile']['title']);
            $this->assertNull($resolved['profile']['slug']);
            $this->assertSame([], $resolved['sections']['about']['value']);
            $this->assertSame('Lufthansa Technik Praktikum 2027', $profile->value('title'));
        } finally {
            Entry::delete($profile);
        }
    }

    public function test_private_asset_rename_updates_application_and_default_document_references(): void
    {
        $this->actingAs($this->user());
        $response = $this->post('/cp/assets', [
            'container' => 'application_documents',
            'folder' => '/',
            'file' => UploadedFile::fake()->createWithContent('rename-source-'.bin2hex(random_bytes(5)).'.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertOk();
        $asset = Asset::find($response->json('data.id'));
        $this->assertNotNull($asset);

        $defaults = GlobalSet::find('application_defaults')->inDefaultSite();
        $originalDefaults = $defaults->data()->all();
        $hadDefaultsFile = is_file($defaults->path());
        $application = Entry::make()->collection('applications')->data([
            'title' => 'Private asset reference test',
            'documents' => ApplicationData::encrypt([['mode' => 'override', 'items' => [
                ['label' => 'Application document', 'file' => [$asset->path()]],
            ]]]),
        ]);
        $application->save();
        $this->entries[] = $application;
        $defaults->set('documents', ApplicationData::encrypt([['items' => [
            ['label' => 'Default document', 'file' => ['application_documents::'.$asset->path()]],
        ]]]));
        $defaults->save();
        $newName = 'renamed-private-'.bin2hex(random_bytes(5));

        try {
            $asset->rename($newName);

            $this->assertSame($newName.'.pdf', ApplicationData::documents(Entry::find($application->id()))[0]['file'][0]);
            $inheritingApplication = Entry::make()->collection('applications')->data([
                'documents' => ApplicationData::encrypt([['mode' => 'inherit']]),
            ]);
            $this->assertSame('application_documents::'.$newName.'.pdf', ApplicationData::documents($inheritingApplication)[0]['file'][0]);
        } finally {
            $defaults->data($originalDefaults);
            if ($hadDefaultsFile) {
                $defaults->save();
            } else {
                $defaults->delete();
            }
            Entry::delete($application);
            $asset->deleteQuietly();
        }
    }
}
