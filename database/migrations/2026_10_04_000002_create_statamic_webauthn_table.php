<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('statamic.users.tables.webauthn', 'webauthn');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('name');
            $table->json('credential')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Keep registered passkeys intact if application migrations are rolled back.
    }
};
