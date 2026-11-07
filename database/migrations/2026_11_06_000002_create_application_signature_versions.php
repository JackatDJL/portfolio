<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_signature_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id')->index();
            $table->string('unsigned_sha256', 64);
            $table->string('signed_sha256', 64)->nullable();
            $table->string('state', 24);
            $table->string('provider', 64)->nullable();
            $table->boolean('sandbox')->default(false);
            $table->text('verification')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });
        Schema::create('application_signature_settings', function (Blueprint $table) {
            $table->uuid('application_id')->primary();
            $table->string('mode', 16)->default('unsigned');
            $table->uuid('active_version_id')->nullable();
            $table->string('updated_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_signature_settings');
        Schema::dropIfExists('application_signature_versions');
    }
};
