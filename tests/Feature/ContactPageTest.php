<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    public function test_disabled_contact_never_sends_or_claims_delivery(): void
    {
        Mail::fake();
        config(['contact.enabled' => false]);
        $this->get('/kontakt?thema=sponsoring')->assertOk()->assertSee('selected>Sponsoring', false)
            ->assertSee('nicht eingerichtet')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/kontakt', $this->payload())->assertStatus(503)->assertSee('nichts versendet');
        Mail::assertNothingSent();
    }

    public function test_log_and_failover_transports_cannot_enable_delivery(): void
    {
        Mail::fake();
        foreach (['log', 'array', 'failover'] as $transport) {
            $this->enable($transport);
            $this->post('/kontakt', $this->payload())->assertStatus(503);
        }
        Mail::assertNothingSent();
    }

    public function test_invalid_fields_are_rendered_without_flashing_private_input(): void
    {
        $this->enable();
        Mail::fake();
        $this->post('/kontakt', ['topic' => 'unknown', 'name' => '<script>alert(1)</script>', 'email' => 'bad', 'message' => 'private'])
            ->assertStatus(422)->assertSee('Bitte prüfe deine Angaben')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSessionMissing('_old_input');
        Mail::assertNothingSent();
    }

    public function test_honeypot_and_missing_or_expired_tokens_block_mail(): void
    {
        $this->enable();
        Mail::fake();
        $this->post('/kontakt', $this->payload())->assertStatus(422);
        $this->withSession(['_token' => 'contact-session-test']);
        $session = $this->app['session']->driver();
        $token = Crypt::encryptString(json_encode(['time' => time() - 8000, 'session' => hash('sha256', 'contact-session-test')]));
        $this->post('/kontakt', [...$this->payload(), 'form_token' => $token])->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_configured_delivery_has_fixed_recipient_and_subject_and_no_session_payload(): void
    {
        $this->enable();
        Mail::fake();
        $this->withSession(['_token' => 'contact-session-test']);
        $session = $this->app['session']->driver();
        $token = Crypt::encryptString(json_encode(['time' => time() - 5, 'session' => hash('sha256', 'contact-session-test')]));
        $this->post('/kontakt', [...$this->payload(), 'form_token' => $token])
            ->assertRedirect('/kontakt')->assertSessionHas('contact_sent', true)->assertSessionMissing('_old_input');
        Mail::assertSent(ContactMessage::class, function ($mail) {
            return $mail->hasTo('recipient@example.org') && $mail->envelope()->subject === 'Kontakt: Projektanfrage'
                && $mail->envelope()->replyTo[0]->address === 'sender@example.org';
        });
        $this->get('/kontakt')->assertSee('Deine Nachricht wurde versendet.');
    }

    public function test_filled_honeypot_and_cross_session_tokens_are_rejected(): void
    {
        $this->enable();
        Mail::fake();
        $this->withSession(['_token' => 'contact-session-test']);
        $token = Crypt::encryptString(json_encode(['time' => time() - 5, 'session' => hash('sha256', 'contact-session-test')]));
        $this->post('/kontakt', [...$this->payload(), 'form_token' => $token, 'website' => 'https://spam.example'])->assertStatus(422);
        $this->withSession(['_token' => 'different-session']);
        $this->post('/kontakt', [...$this->payload(), 'form_token' => $token])->assertStatus(422);
        $this->post('/kontakt', [...$this->payload(), 'form_token' => ['bad']])->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_transport_failure_does_not_claim_delivery_or_store_message(): void
    {
        $this->enable();
        $this->withSession(['_token' => 'contact-session-test']);
        $token = Crypt::encryptString(json_encode(['time' => time() - 5, 'session' => hash('sha256', 'contact-session-test')]));
        $pending = \Mockery::mock();
        Mail::shouldReceive('mailer')->with('contact-test')->once()->andReturn($pending);
        $pending->shouldReceive('to')->with('recipient@example.org')->once()->andReturnSelf();
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('private transport diagnostics'));
        $this->post('/kontakt', [...$this->payload(), 'form_token' => $token])->assertStatus(503)
            ->assertSee('Versand ist fehlgeschlagen')->assertDontSee('private transport diagnostics')
            ->assertSessionMissing('contact_sent')->assertSessionMissing('_old_input');
    }

    public function test_repeated_posts_are_rate_limited_even_when_delivery_is_disabled(): void
    {
        config(['contact.enabled' => false]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/kontakt', $this->payload())->assertStatus(503);
        }
        $this->post('/kontakt', $this->payload())->assertStatus(429);
    }

    public function test_topic_query_does_not_accept_arbitrary_values(): void
    {
        $this->get('/kontakt?thema[]=politik')->assertOk()->assertSee('selected>Allgemeiner Kontakt', false);
    }

    private function enable(string $transport = 'smtp'): void
    {
        config([
            'contact.enabled' => true, 'contact.recipient' => 'recipient@example.org', 'contact.mailer' => 'contact-test',
            'mail.mailers.contact-test' => ['transport' => $transport], 'mail.from.address' => 'website@example.org',
        ]);
    }

    private function payload(): array
    {
        return ['topic' => 'projekt', 'name' => 'Test', 'email' => 'sender@example.org', 'message' => 'Eine konkrete Projektanfrage.', 'website' => ''];
    }
}
