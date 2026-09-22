<?php

namespace Tests\Feature;

use App\Fieldtypes\ProtectedText;
use App\Support\CvCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Tests\TestCase;

class CvPrivateDataTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINELS = [
        'private_email' => 'PRIVATE-CV-EMAIL-SENTINEL@example.invalid',
        'phone' => 'PRIVATE-CV-PHONE-SENTINEL',
        'street' => 'PRIVATE-CV-ADDRESS-SENTINEL',
        'house_number' => '1',
        'postal_code' => '21600',
        'city' => 'Teststadt',
    ];

    public function test_protected_fieldtype_encrypts_at_rest_and_decrypts_for_the_control_panel(): void
    {
        $fieldtype = new ProtectedText;
        $ciphertext = $fieldtype->process(self::SENTINELS['private_email']);

        $this->assertNotSame(self::SENTINELS['private_email'], $ciphertext);
        $this->assertSame(self::SENTINELS['private_email'], Crypt::decryptString($ciphertext));
        $this->assertSame(self::SENTINELS['private_email'], $fieldtype->preProcess($ciphertext));
    }

    public function test_public_cv_contains_synthetic_masks_but_no_private_plaintext(): void
    {
        $response = $this->get('/cv');

        $response->assertOk();
        $response->assertSee('Geschützte Angabe');
        $response->assertSee('E-Mail', false);
        $response->assertSee('Telefon', false);
        $response->assertSee('Anschrift', false);
        foreach (array_slice(self::SENTINELS, 0, 3) as $sentinel) {
            $response->assertDontSee($sentinel);
        }
    }

    public function test_valid_capability_reveals_only_decrypted_cv_global_values(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = $variables->data()->all();
        $encrypted = collect(self::SENTINELS)->map(fn ($value) => Crypt::encryptString($value))->all();
        $variables->data(array_merge($original, $encrypted));
        $capability = CvCapabilities::issueTemporary('/cv');

        try {
            $this->postJson('/cv/token-exchange', ['token' => $capability['token'], 'path' => '/cv'])->assertOk();
            $response = $this->postJson('/cv/private-data', ['path' => '/cv']);
            $response->assertOk()->assertJson(self::SENTINELS);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        } finally {
            $variables->data($original);
        }
    }

    public function test_invalid_or_wrong_profile_capability_is_rejected(): void
    {
        $capability = CvCapabilities::issueTemporary('/cv/jobmesse-26');

        $this->postJson('/cv/token-exchange', ['token' => 'wrong', 'path' => '/cv'])->assertUnprocessable();
        $this->postJson('/cv/token-exchange', ['token' => $capability['token'], 'path' => '/cv'])->assertNotFound();
        $this->postJson('/cv/token-exchange', ['token' => $capability['token'], 'path' => '/cv/jobmesse-26'])->assertOk();
        $this->postJson('/cv/private-data', ['path' => '/cv/jobmesse-26'])->assertOk();
    }

    public function test_control_panel_utility_issues_a_real_profile_scoped_capability(): void
    {
        $this->actingAs(User::findByEmail('jack@djl.foundation'));

        $response = $this->postJson('/cp/cv/private-link/temporary', ['path' => '/cv/jobmesse-26'])->assertOk();
        preg_match('/#cv=([A-Za-z0-9]+)/', $response->json('url'), $matches);
        $this->postJson('/cv/token-exchange', [
            'token' => $matches[1],
            'path' => '/cv/jobmesse-26',
        ])->assertOk();
    }

    public function test_permanent_profile_link_is_stable_scoped_and_revocable(): void
    {
        $this->actingAs(User::findByEmail('jack@djl.foundation'));
        $first = $this->postJson('/cp/cv/private-link/permanent', ['path' => '/cv/jobmesse-26'])->assertOk();
        $second = $this->postJson('/cp/cv/private-link/permanent', ['path' => '/cv/jobmesse-26'])->assertOk();
        $this->assertSame($first->json('url'), $second->json('url'));
        preg_match('/#cv=([A-Za-z0-9]+)/', $first->json('url'), $matches);
        $this->postJson('/cv/token-exchange', ['token' => $matches[1], 'path' => '/cv'])->assertNotFound();
        $this->deleteJson('/cp/cv/private-link/permanent', ['path' => '/cv/jobmesse-26'])->assertOk()->assertJson(['revoked' => true]);
        $this->postJson('/cv/token-exchange', ['token' => $matches[1], 'path' => '/cv/jobmesse-26'])->assertNotFound();
        $this->assertNotEmpty(DB::table('cv_access_tokens')->where('identifier', $first->json('identifier'))->value('revoked_at'));
    }

    public function test_temporary_link_expires(): void
    {
        $capability = CvCapabilities::issueTemporary('/cv/jobmesse-26');
        DB::table('cv_access_tokens')->where('identifier', $capability['identifier'])->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/cv/token-exchange', ['token' => $capability['token'], 'path' => '/cv/jobmesse-26'])->assertNotFound();
    }

    public function test_control_panel_qr_uses_canonical_public_and_active_private_urls(): void
    {
        $this->actingAs(User::findByEmail('jack@djl.foundation'));
        $public = $this->get('/cp/cv/private-link/qr?path=/cv/jobmesse-26&source=public')->assertOk();
        $this->assertStringContainsString('<svg', $public->getContent());
        $this->assertStringContainsString('image/svg+xml', $public->headers->get('Content-Type'));
        $this->get('/cp/cv/private-link/qr?path=/cv/jobmesse-26&source=temporary')->assertNotFound();

        $link = $this->postJson('/cp/cv/private-link/temporary', ['path' => '/cv/jobmesse-26'])->assertOk();
        $this->assertStringContainsString('/cv/jobmesse-26#cv=', $link->json('url'));
        $private = $this->get('/cp/cv/private-link/qr?path=/cv/jobmesse-26&source=temporary')->assertOk();
        $this->assertStringContainsString('<svg', $private->getContent());
        $this->assertStringContainsString('no-store', $private->headers->get('Cache-Control'));
        DB::table('cv_access_tokens')->where('identifier', $link->json('identifier'))->update(['expires_at' => now()->subMinute()]);
        $this->get('/cp/cv/private-link/qr?path=/cv/jobmesse-26&source=temporary')->assertNotFound();
    }
}
