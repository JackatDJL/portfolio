<?php

namespace Tests\Feature;

use App\Fieldtypes\ProtectedText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Tests\TestCase;

class CvPrivateDataTest extends TestCase
{
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
        Cache::put('cv-capability:'.hash('sha256', 'test-capability'), ['path' => '/cv'], now()->addMinute());

        try {
            $response = $this->postJson('/cv/private-data', ['token' => 'test-capability', 'path' => '/cv']);
            $response->assertOk()->assertJson(self::SENTINELS);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        } finally {
            $variables->data($original);
        }
    }

    public function test_invalid_or_wrong_profile_capability_is_rejected(): void
    {
        Cache::put('cv-capability:'.hash('sha256', 'profile-capability'), ['path' => '/cv/airbus-26'], now()->addMinute());

        $this->postJson('/cv/private-data', ['token' => 'wrong', 'path' => '/cv'])->assertNotFound();
        $this->postJson('/cv/private-data', ['token' => 'profile-capability', 'path' => '/cv'])->assertNotFound();
        $this->postJson('/cv/private-data', ['token' => 'profile-capability', 'path' => '/cv/airbus-26'])->assertOk();
    }

    public function test_control_panel_utility_issues_a_real_profile_scoped_capability(): void
    {
        $this->actingAs(User::findByEmail('jack@djl.foundation'));

        $response = $this->get('/cp/cv/private-link?path=/cv/airbus-26');
        $response->assertRedirectContains('/cv/airbus-26#cv=');
        preg_match('/#cv=([A-Za-z0-9]+)/', (string) $response->headers->get('Location'), $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->postJson('/cv/private-data', [
            'token' => $matches[1],
            'path' => '/cv/airbus-26',
        ])->assertOk();
    }

}
