<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('access_token')->nullable();
            $table->string('client_id');
            $table->string('client_secret');
            $table->timestamp('expired_at')->nullable();
            $table->morphs('modelable');
            $table->json('scopes')->nullable();
            $table->string('token_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_oauth_tokens');
    }
};
