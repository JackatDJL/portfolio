<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_recipient_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('application_id');
            $table->uuid('grant_id');
            $table->string('event', 32);
            $table->string('path', 512)->nullable();
            $table->timestamp('created_at');
            $table->index(['application_id', 'id']);
            $table->index(['grant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_recipient_events');
    }
};
