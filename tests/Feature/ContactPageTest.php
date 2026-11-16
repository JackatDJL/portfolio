<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    public function test_contact_document_title_falls_back_when_optional_seo_title_is_empty(): void
    {
        $html = $this->get('/kontakt')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<title>Kontakt<\/title>/', $html);
    }

    public function test_disabled_contact_never_sends_or_claims_delivery(): void
    {
        Mail::fake();
        config(['contact.enabled' => false]);
        $this->get('/kontakt?thema=sponsoring')->assertOk()->assertSee('mailto:jack@djl.foundation?subject=Sponsoring', false)
            ->assertDontSee('<form', false)->assertDontSee(' disabled', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/kontakt', $this->payload())->assertStatus(503)->assertSee('nichts versendet')->assertDontSee('href="#contact-topic"', false);
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

    public function test_url_overrides_cannot_select_discarding_or_logging_transports(): void
    {
        $this->enable();
        Mail::fake();
        foreach (['array://localhost', 'log://localhost', 'smtp://localhost?driver=log'] as $url) {
            config(['mail.mailers.contact-test.url' => $url]);
            $this->post('/kontakt', $this->payload())->assertStatus(503);
        }
        Mail::assertNothingSent();
        config(['mail.mailers.contact-test.url' => 'smtp://localhost:2525']);
        $this->get('/kontakt')->assertOk()->assertDontSee('Nachrichtenversand ist derzeit nicht eingerichtet');
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
        $this->enable();
        $this->get('/kontakt?thema[]=politik')->assertOk()->assertSee('selected>Allgemeiner Kontakt', false);
    }

    public function test_unpublished_entry_blocks_post_before_mail_is_sent(): void
    {
        $this->enable();
        Mail::fake();
        $entry = clone Entry::find('633a4a23-b229-4508-87f2-c79814bd6d92');
        $entry->published(false);
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($entry);
        Entry::shouldReceive('query')->andReturn($query);
        $this->post('/kontakt', $this->payload())->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_editorial_metadata_reaches_layout_while_noindex_is_enforced(): void
    {
        $entry = clone Entry::find('633a4a23-b229-4508-87f2-c79814bd6d92');
        $entry->set('seo_title', 'Kontakt Testtitel')->set('seo_description', 'Bearbeitete Beschreibung');
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($entry);
        Entry::shouldReceive('query')->andReturn($query);
        $this->get('/kontakt')->assertOk()->assertSee('Kontakt Testtitel')->assertSee('Bearbeitete Beschreibung')
            ->assertSee('noindex, nofollow');
    }

    public function test_plain_text_mime_preserves_punctuation_without_html_entities(): void
    {
        $sent = Mail::mailer('array')->to('recipient@example.org')->send(new ContactMessage('Projektanfrage', "O'Connor & Partner", 'sender@example.org', 'A < B & C'));
        $body = $sent->getSymfonySentMessage()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString("O'Connor & Partner", $body);
        $this->assertStringContainsString('A < B & C', $body);
        $this->assertStringNotContainsString('&amp;', $body);
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
