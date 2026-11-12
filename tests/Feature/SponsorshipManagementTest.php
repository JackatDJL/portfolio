<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\User;
use Tests\TestCase;

class SponsorshipManagementTest extends TestCase
{
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic.editions.pro' => true]);
        Schema::dropIfExists('sponsorship_records');
        Schema::create('sponsorship_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('funding_category', 24)->index();
            $table->longText('encrypted_payload');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sponsorship_records');
        foreach ($this->users as $user) {
            $user->delete();
        }
        parent::tearDown();
    }

    private function user(bool $super = true)
    {
        $user = User::make()->id('sponsor-test-'.bin2hex(random_bytes(6)))->email(bin2hex(random_bytes(6)).'@example.invalid');
        if ($super) {
            $user->makeSuper();
        }
        $user->save();
        $this->users[] = $user;

        return $user;
    }

    private function values(array $overrides = []): array
    {
        return array_merge([
            'funding_category' => 'personal', 'organisation' => 'PRIVATE-ORG-SENTINEL', 'contact' => 'PRIVATE-CONTACT-SENTINEL',
            'email' => 'private@example.invalid', 'status' => 'negotiating', 'follow_up_date' => '2026-12-01',
            'correspondence' => 'PRIVATE-CORRESPONDENCE-SENTINEL', 'pledged_amount' => '500.00', 'received_amount' => '125.50',
            'received_confirmed' => '1', 'contribution_kind' => 'monetary', 'in_kind_description' => '',
            'restricted_purpose' => 'PRIVATE-RESTRICTION-SENTINEL', 'allocated_project' => 'software', 'allocated_amount' => '100.00',
            'expenses' => '2026-11-12 | Bauteile | 12.50', 'internal_notes' => 'PRIVATE-NOTE-SENTINEL',
            'acknowledgment_permission' => '0',
        ], $overrides);
    }

    public function test_guests_and_non_superusers_cannot_read_or_write_sponsorship_records(): void
    {
        $this->get('/cp/sponsorships')->assertRedirect();
        $this->actingAs($this->user(false));
        $this->get('/cp/sponsorships')->assertRedirect();
        $this->post('/cp/sponsorships', $this->values())->assertRedirect();
        $this->assertSame(0, DB::table('sponsorship_records')->count());
    }

    public function test_sensitive_fields_are_encrypted_and_school_records_remain_categorized(): void
    {
        $this->actingAs($this->user());
        $payload = $this->values(['funding_category' => 'school', 'allocated_project' => 'atheblues']);
        $this->post('/cp/sponsorships', $payload)->assertRedirect();
        $row = DB::table('sponsorship_records')->first();
        $this->assertSame('school', $row->funding_category);
        foreach (['PRIVATE-ORG-SENTINEL', 'PRIVATE-CONTACT-SENTINEL', 'PRIVATE-CORRESPONDENCE-SENTINEL', 'PRIVATE-RESTRICTION-SENTINEL', 'PRIVATE-NOTE-SENTINEL'] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $row->encrypted_payload);
        }
        $data = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('PRIVATE-ORG-SENTINEL', $data['organisation']);
        $this->assertSame(50000, $data['pledged_cents']);
        $this->assertSame(12550, $data['received_cents']);
        $this->assertSame(10000, $data['allocated_cents']);
        $this->assertSame(1250, $data['expenses'][0]['amount_cents']);
        $this->get('/cp/sponsorships')->assertOk()->assertSee('87,50 €');
    }

    public function test_project_category_and_expense_limits_are_enforced(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values(['funding_category' => 'company', 'allocated_project' => 'atheblues']))->assertSessionHasErrors('allocated_project');
        $this->post('/cp/sponsorships', $this->values(['expenses' => '2026-11-12 | Over budget | 200.00']))->assertStatus(422);
        $this->assertSame(0, DB::table('sponsorship_records')->count());
    }

    public function test_documents_are_stored_on_private_disk_and_download_requires_superuser(): void
    {
        Storage::fake('funding_documents');
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values() + ['document_label' => 'Invoice', 'documents' => [UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')]])->assertRedirect();
        $row = DB::table('sponsorship_records')->first();
        $data = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Invoice', $data['documents'][0]['label']);
        Storage::disk('funding_documents')->assertExists($data['documents'][0]['path']);
        $this->get('/cp/sponsorships/'.$row->id.'/documents/0')->assertOk();
        $this->actingAs($this->user(false));
        $this->get('/cp/sponsorships/'.$row->id.'/documents/0')->assertRedirect();
        $this->get('/cp/sponsorships/export.csv')->assertRedirect();
    }

    public function test_public_totals_include_only_confirmed_money_not_pledges_or_in_kind(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values(['received_confirmed' => '0', 'allocated_amount' => '0', 'allocated_project' => '', 'expenses' => '']))->assertRedirect();
        $this->post('/cp/sponsorships', $this->values(['organisation' => 'In-kind', 'contribution_kind' => 'in_kind', 'received_amount' => '250.00', 'allocated_amount' => '0', 'allocated_project' => '', 'expenses' => '']))->assertRedirect();
        $this->post('/cp/sponsorships', $this->values(['organisation' => 'Confirmed', 'received_amount' => '50.00', 'allocated_amount' => '0', 'allocated_project' => '', 'expenses' => '', 'acknowledgment_permission' => '1']))->assertRedirect();
        $this->post('/cp/sponsorships', $this->values(['funding_category' => 'company', 'organisation' => 'Company-PRIVATE-SENTINEL', 'allocated_project' => 'rosenfold', 'allocated_amount' => '0', 'received_amount' => '30.00', 'expenses' => '', 'acknowledgment_permission' => '0']))->assertRedirect();
        Auth::logout();
        $this->get('/support')->assertOk()->assertSee('Bestätigt: 50 €')->assertSee('Bestätigt: 30 €')->assertSee('Bestätigt: 0 €')
            ->assertSee('Confirmed')->assertDontSee('Company-PRIVATE-SENTINEL');
    }

    public function test_category_cannot_be_changed_after_a_record_is_created(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values())->assertRedirect();
        $row = DB::table('sponsorship_records')->first();
        $this->put('/cp/sponsorships/'.$row->id, $this->values(['funding_category' => 'company', 'allocated_project' => 'rosenfold']))->assertStatus(422);
        $this->assertSame('personal', DB::table('sponsorship_records')->where('id', $row->id)->value('funding_category'));
    }

    public function test_unconfirming_received_funds_cannot_silently_remove_existing_allocations(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values())->assertRedirect();
        $row = DB::table('sponsorship_records')->first();
        $this->put('/cp/sponsorships/'.$row->id, $this->values(['received_confirmed' => '0']))->assertStatus(422);
        $data = json_decode(Crypt::decryptString(DB::table('sponsorship_records')->where('id', $row->id)->value('encrypted_payload')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(10000, $data['allocated_cents']);
    }

    public function test_superuser_can_export_individual_accounting_records(): void
    {
        $this->actingAs($this->user());
        $this->post('/cp/sponsorships', $this->values())->assertRedirect();
        $this->get('/cp/sponsorships/export.csv')->assertDownload('sponsorship-accounting.csv');
    }
}
