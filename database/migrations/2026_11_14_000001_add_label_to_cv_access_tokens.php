<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_access_tokens', function (Blueprint $table): void {
            $table->text('label')->nullable();
            $table->string('label_key', 64)->nullable();
            $table->unique(['profile_path', 'label_key']);
        });
    }

    public function down(): void
    {
        Schema::table('cv_access_tokens', function (Blueprint $table): void {
            $table->dropUnique(['profile_path', 'label_key']);
            $table->dropColumn(['label', 'label_key']);
        });
    }
};
