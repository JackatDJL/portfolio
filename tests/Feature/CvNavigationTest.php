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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Statamic\StaticCaching\DefaultUrlExcluder;
use Tests\Support\ApplicationPdfFixture;
use Tests\Support\HandwrittenSignatureFixture;
use Tests\TestCase;

class CvNavigationTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationPdfFixture $fixture;
    private array $users = [];
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new ApplicationPdfFixture;
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) $user->delete();
        foreach ($this->roles as $role) \Statamic\Facades\Role::delete($role);
        $this->fixture->cleanup();
        parent::tearDown();
    }

    private function limitedCpUser(): object
    {
        $role = \Statamic\Facades\Role::make()
            ->handle('cv-grant-limited-'.bin2hex(random_bytes(6)))
            ->title('Limited CV link test')
            ->addPermission('access cp');
        \Statamic\Facades\Role::save($role);
        $this->roles[] = $role;

        $user = User::make()->id('cv-grant-limited-'.bin2hex(random_bytes(6)))->email(bin2hex(random_bytes(6)).'@example.invalid')->assignRole($role);
        $user->save();
        $this->users[] = $user;

        return $user;
    }

    public function test_detail_return_context_preserves_the_profile_and_existing_authorization(): void
    {
        $grant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $grant['token']])->assertOk();
        $cv = app(CvViewModel::class)->make('jobmesse-26', true, $grant['token']);
        $experienceUrl = $cv['experience'][0]['url'];
        $this->assertStringContainsString('cv_return=%2Fcv%2Fjobmesse-26#cv='.$grant['token'], $experienceUrl);
        $experiencePath = parse_url($experienceUrl, PHP_URL_PATH).'?'.parse_url($experienceUrl, PHP_URL_QUERY);
        $this->get($experiencePath)->assertOk()->assertSee('href="/cv/jobmesse-26"', false);
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

    public function test_experience_detail_has_a_safe_cv_return_for_direct_visits(): void
    {
        $this->get('/cv/exp/atheblues-robotik-und-teamarbeit')
            ->assertOk()
            ->assertSee('href="/cv"', false)
            ->assertDontSee('<span>Erfahrung</span>', false)
            ->assertDontSee('experience-hero__image', false);
    }

    public function test_return_context_cannot_redirect_to_arbitrary_urls(): void
    {
        foreach (['https://example.org', '//example.org', '/cv/jobmesse-26#cv=secret', '/cv/jobmesse-26?token=secret', '/cv/exp/test', '/cv/nonexistent-profile'] as $path) {
            $this->assertSame('/cv', CvNavigation::returnPath($path));
        }
        $this->assertSame('/cv/jobmesse-26', CvNavigation::returnPath('/cv/jobmesse-26'));
    }

    public function test_application_data_payload_omits_birth_fields_but_cv_grants_keep_their_existing_payload(): void
    {
        $cv = GlobalSet::find('cv')->inDefaultSite();
        $cv->set('date_of_birth', Crypt::encryptString('2000-01-02'));
        $cv->set('place_of_birth', Crypt::encryptString('Synthetic birthplace'));
        $this->fixture->entry->save();
        $id = $this->fixture->entry->id();
        $grant = ApplicationCapabilities::issue($id, 'Payload test');
        $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $grant['token']])->assertOk();
        $this->postJson('/cv/private-data', ['path' => '/applications/'.$id.'/cv'])
            ->assertOk()
            ->assertJsonPath('private_email', 'private-export@example.invalid')
            ->assertJsonPath('signature_mode', 'typed')
            ->assertJsonMissingPath('date_of_birth')
            ->assertJsonMissingPath('place_of_birth');

        $cvGrant = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        $this->postJson('/cv/token-exchange', ['path' => '/cv/jobmesse-26', 'token' => $cvGrant['token']])->assertOk();
        $this->postJson('/cv/private-data', ['path' => '/cv/jobmesse-26'])
            ->assertOk()
            ->assertJsonPath('date_of_birth', '02.01.2000')
            ->assertJsonPath('place_of_birth', 'Synthetic birthplace');
    }

    public function test_original_hero_override_bypasses_static_caching(): void
    {
        $excluder = new CvNavigationUrlExcluder(new DefaultUrlExcluder('https://example.invalid', ['/applications*']));
        $this->assertTrue($excluder->isExcluded('https://example.invalid/projekte/erstwaehlerforum-stade?hero=original'));
        $this->assertFalse($excluder->isExcluded('https://example.invalid/projekte/erstwaehlerforum-stade'));
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
            $model = app(CvViewModel::class)->forApplication($fixture->entry);
            $path = '/cv/'.$model['profile']['slug'];
            $token = substr(parse_url($model['interactive_url'], PHP_URL_FRAGMENT), 3);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
            $this->assertTrue(CvCapabilities::allows($token, $path));
            $this->assertStringContainsString($path.'#cv='.$token, $model['interactive_url']);
            $this->assertStringContainsString(http_build_query(['cv_return' => $path], '', '&', PHP_QUERY_RFC3986).'#cv='.$token, $model['education'][0]['url']);
            $permanent = app(CvViewModel::class)->forApplication($fixture->entry, permanentLink: true);
            $again = app(CvViewModel::class)->forApplication($fixture->entry, permanentLink: true);
            $this->assertSame($permanent['interactive_url'], $again['interactive_url']);
            $this->assertStringContainsString($path, $permanent['interactive_url']);
            $this->assertCount(1, array_filter(\App\Support\CvCapabilities::namedGrants($path), fn ($grant) => $grant['kind'] === 'permanent'));
            $permanentToken = substr(parse_url($permanent['interactive_url'], PHP_URL_FRAGMENT), 3);
            $this->assertTrue(CvCapabilities::allows($permanentToken, $path));
            $applicationPath = '/applications/'.$id.'/cv';
            $this->get($applicationPath)->assertNotFound();
            $this->get($applicationPath.'/pdf')->assertNotFound();
            app(PrivateHandwrittenSignature::class)->save('data:image/png;base64,'.base64_encode(HandwrittenSignatureFixture::png()));
            $fixture->entry->set('signature', ApplicationData::encrypt([['mode' => 'handwritten', 'width_mm' => 46, 'baseline_mm' => 0]]));
            $fixture->entry->save();
            $grant = ApplicationCapabilities::issue($id, 'Navigation test');
            $this->postJson('/applications/token-exchange', ['id' => $id, 'token' => $grant['token']])->assertOk();
            $this->get($applicationPath)->assertOk()->assertSee('data-cv-private-panel', false)->assertDontSee('Lebenslauf zur Bewerbung');
            $this->postJson('/cv/private-data', ['path' => $applicationPath])->assertOk();
            $this->post('/cv/private-signature', ['path' => $applicationPath])->assertOk()->assertHeader('Content-Type', 'image/png');
            $pdf = $fixture->root.'/route-fixture.pdf';
            file_put_contents($pdf, '%PDF-1.4');
            $this->mock(CvPdfRenderer::class)->shouldReceive('renderApplication')->once()->andReturn($pdf);
            $this->get($applicationPath.'/pdf')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
            ApplicationCapabilities::revoke($id, $grant['id']);
            $this->get($applicationPath)->assertNotFound();
            $this->postJson('/cv/private-data', ['path' => $applicationPath])->assertNotFound();
            $this->post('/cv/private-signature', ['path' => $applicationPath])->assertNotFound();
            $this->get($applicationPath.'/pdf')->assertNotFound();
        } finally {
            $fixture->cleanup();
        }
    }

    public function test_application_preview_reuses_one_temporary_cv_grant_and_rotates_it_after_expiry(): void
    {
        $this->fixture->entry->save();
        $first = app(CvViewModel::class)->forApplication($this->fixture->entry);
        $path = '/cv/'.$first['profile']['slug'];
        $again = app(CvViewModel::class)->forApplication($this->fixture->entry);
        $firstToken = substr(parse_url($first['interactive_url'], PHP_URL_FRAGMENT), 3);

        $this->assertSame($first['interactive_url'], $again['interactive_url']);
        $this->assertTrue(CvCapabilities::allows($firstToken, $path));
        $this->assertSame(1, DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'temporary')->whereNull('revoked_at')->count());

        $this->travel(16)->minutes();
        $next = app(CvViewModel::class)->forApplication($this->fixture->entry);
        $nextToken = substr(parse_url($next['interactive_url'], PHP_URL_FRAGMENT), 3);

        $this->assertNotSame($firstToken, $nextToken);
        $this->assertFalse(CvCapabilities::allows($firstToken, $path));
        $this->assertTrue(CvCapabilities::allows($nextToken, $path));
        $this->assertSame(1, DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'temporary')->whereNull('revoked_at')->count());
        $this->assertSame(2, DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'temporary')->count());
        $this->assertNotNull(DB::table('cv_access_tokens')->where('token_hash', hash('sha256', $firstToken))->value('revoked_at'));
    }

    public function test_named_cv_grant_is_looked_up_and_created_inside_an_atomic_lock(): void
    {
        $cache = app('cache');
        try {
            $lock = \Mockery::mock();
            $lock->shouldReceive('block')->once()->with(10, \Mockery::type(\Closure::class))
                ->andReturnUsing(function (int $seconds, \Closure $createGrant) {
                    $this->assertSame(0, DB::table('cv_access_tokens')->count(), 'The lock must be acquired before grant lookup/insertion.');

                    return $createGrant();
                });
            Cache::shouldReceive('lock')->once()
                ->withArgs(fn (string $name, int $seconds): bool => str_starts_with($name, 'cv-grant:') && $seconds === 30)
                ->andReturn($lock);

            $grant = CvCapabilities::permanentForLabel('/cv/jobmesse-26', 'Bewerbungs Lebenslauf für 2027-001');

            $this->assertSame('permanent', $grant['kind']);
            $this->assertDatabaseCount('cv_access_tokens', 1);
        } finally {
            Cache::swap($cache);
        }
    }

    public function test_cv_profile_manager_creates_independent_named_grants_and_revokes_only_one(): void
    {
        $profile = \Statamic\Facades\Entry::query()->where('collection', 'cv_profiles')->where('slug', 'jobmesse-26')->first();
        $this->assertNotNull($profile);
        $endpoint = '/cp/cv/private-link/grants?path='.urlencode('/cv/jobmesse-26');
        $this->getJson($endpoint)->assertUnauthorized();
        $limited = User::make()->id('cv-grant-limited-'.bin2hex(random_bytes(6)))->email(bin2hex(random_bytes(6)).'@example.invalid');
        $limited->save();
        $this->users[] = $limited;
        $this->actingAs($limited)->getJson($endpoint)->assertForbidden();
        $user = User::make()->id('cv-grant-manager-'.bin2hex(random_bytes(6)))->email(bin2hex(random_bytes(6)).'@example.invalid')->makeSuper();
        $user->save();
        $this->users[] = $user;
        $this->actingAs($user);
        $path = '/cv/jobmesse-26';

        $first = $this->postJson('/cp/cv/private-link/grants', ['path' => $path, 'label' => 'Bewerbungs Lebenslauf für 2027-001', 'kind' => 'permanent'])->assertOk()->json();
        $second = $this->postJson('/cp/cv/private-link/grants', ['path' => $path, 'label' => 'Bewerbungs Lebenslauf für 2027-001', 'kind' => 'permanent'])->assertOk()->json();
        $firstToken = substr(parse_url($first['url'], PHP_URL_FRAGMENT), 3);
        $secondToken = substr(parse_url($second['url'], PHP_URL_FRAGMENT), 3);
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertTrue(CvCapabilities::allows($firstToken, $path));
        $this->assertTrue(CvCapabilities::allows($secondToken, $path));

        $list = $this->getJson('/cp/cv/private-link/grants?path='.urlencode($path))->assertOk()->json('grants');
        $this->assertCount(2, $list);
        $this->assertStringNotContainsString($firstToken, json_encode($list));
        $this->assertStringNotContainsString($secondToken, json_encode($list));
        $this->deleteJson('/cp/cv/private-link/grants/'.$first['grant']['id'].'?path='.urlencode($path))->assertOk();
        $this->assertFalse(CvCapabilities::allows($firstToken, $path));
        $this->assertTrue(CvCapabilities::allows($secondToken, $path));
    }

    public function test_legacy_cv_link_endpoints_never_expose_named_grants_to_regular_cp_users(): void
    {
        $path = '/cv/jobmesse-26';
        CvCapabilities::temporaryForLabel($path, 'Bewerbungs Lebenslauf für 2027-001');
        CvCapabilities::permanentForLabel($path, 'Bewerbungs Lebenslauf für 2027-001');

        $this->actingAs($this->limitedCpUser());

        $this->getJson('/cp/cv/private-link/status?path='.urlencode($path))
            ->assertOk()
            ->assertExactJson(['path' => $path, 'links' => []]);
        $this->get('/cp/cv/private-link/qr?path='.urlencode($path).'&source=temporary')->assertNotFound();
        $this->get('/cp/cv/private-link/qr?path='.urlencode($path).'&source=permanent')->assertNotFound();
        $this->getJson('/cp/cv/private-link/grants?path='.urlencode($path))->assertForbidden();
    }

    public function test_legacy_link_replacement_and_revocation_leave_named_application_grants_active(): void
    {
        $path = '/cv/jobmesse-26';
        $label = 'Bewerbungs Lebenslauf für 2027-001';
        $namedTemporary = CvCapabilities::temporaryForLabel($path, $label);
        $namedPermanent = CvCapabilities::permanentForLabel($path, $label);
        $legacyTemporary = CvCapabilities::issueTemporary($path);
        $legacyPermanent = CvCapabilities::permanent($path);

        $this->actingAs($this->limitedCpUser());

        $this->postJson('/cp/cv/private-link/temporary', ['path' => $path, 'replace' => true])->assertOk();
        $this->postJson('/cp/cv/private-link/permanent', ['path' => $path, 'replace' => true])->assertOk();
        $this->assertFalse(CvCapabilities::allows($legacyTemporary['token'], $path));
        $this->assertFalse(CvCapabilities::allows($legacyPermanent['token'], $path));
        $this->assertTrue(CvCapabilities::allows($namedTemporary['token'], $path));
        $this->assertTrue(CvCapabilities::allows($namedPermanent['token'], $path));

        $this->assertTrue(CvCapabilities::revokePermanent($path));
        $this->assertTrue(CvCapabilities::allows($namedPermanent['token'], $path));
        $this->assertSame($namedTemporary['token'], CvCapabilities::temporaryForLabel($path, $label)['token']);
        $this->assertSame($namedPermanent['token'], CvCapabilities::permanentForLabel($path, $label)['token']);
    }

    public function test_unpublished_connected_profile_gets_no_public_cv_grant_or_return_links(): void
    {
        $profile = \Statamic\Facades\Entry::make()->collection('cv_profiles')->slug('private-draft-'.bin2hex(random_bytes(5)))->published(false)->data(['title' => 'Private draft profile']);
        $profile->save();
        try {
            $this->fixture->entry->set('cv', ApplicationData::encrypt([['profile' => [$profile->id()]]]));
            $model = app(CvViewModel::class)->forApplication($this->fixture->entry);
            $this->assertNull($model['interactive_url']);
            $this->assertStringNotContainsString('#cv=', json_encode($model));
            foreach (['experience', 'education', 'projects', 'publications'] as $section) {
                foreach ($model[$section] as $entry) $this->assertStringNotContainsString('cv_return=', $entry['url']);
            }
            $this->assertSame([], \App\Support\CvCapabilities::namedGrants('/cv/'.$profile->slug()));
        } finally {
            \Statamic\Facades\Entry::delete($profile);
        }
    }

    public function test_cv_profile_detail_templates_keep_back_links_in_the_page_actions(): void
    {
        $project = file_get_contents(resource_path('views/projects/show.antlers.html'));
        $publication = file_get_contents(resource_path('views/partials/publications/header.antlers.html'));
        $this->assertStringContainsString('partial:detail/layout :intro="_intro"', $project);
        $this->assertMatchesRegularExpression('/project-detail__reading.*?project-detail__actions.*?← Lebenslauf.*?project-detail__prose.*?partial:components\/table-of-contents/s', $project, 'Project actions, including the CV return link, belong before the article prose.');
        $this->assertStringContainsString('← Lebenslauf', $publication, 'Publication return control belongs in the existing header action row.');
    }
}
