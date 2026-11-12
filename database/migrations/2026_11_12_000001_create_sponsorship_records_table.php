<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('funding_category', 24)->index();
            $table->longText('encrypted_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_records');
    }
};
