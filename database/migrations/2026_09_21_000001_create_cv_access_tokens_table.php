<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('profile_path')->index();
            $table->string('kind', 16);
            $table->string('identifier', 24)->unique();
            $table->string('token_hash', 64)->unique();
            $table->text('encrypted_token');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_access_tokens');
    }
};
