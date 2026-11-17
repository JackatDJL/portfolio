<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_recipient_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id')->index();
            $table->text('label');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'revoked_at']);
        });

        // Preserve every existing bearer URL by copying its hash and state. The
        // legacy table remains intact for rollback and audit purposes.
        if (Schema::hasTable('application_access_tokens')) {
            DB::table('application_access_tokens')->orderBy('application_id')->get()->each(function (object $legacy): void {
                DB::table('application_recipient_grants')->insert([
                    'id' => (string) Str::uuid(),
                    'application_id' => $legacy->application_id,
                    'label' => Crypt::encryptString('Vorhandener Empfängerlink'),
                    'token_hash' => $legacy->token_hash,
                    'expires_at' => $legacy->expires_at,
                    'last_accessed_at' => null,
                    'revoked_at' => $legacy->revoked_at,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_recipient_grants');
    }
};
