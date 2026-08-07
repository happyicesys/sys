<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary observation table for App\Http\Middleware\LogDeliveryPlatformAuth.
 *
 * Additive only - nothing existing reads or writes it. Drop it (and the
 * middleware) once we know whether Grab attaches its bearer token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_platform_auth_probes', function (Blueprint $table) {
            $table->id();
            $table->string('route', 191)->index();
            $table->string('method', 10);
            $table->boolean('has_auth_header')->default(false)->index();
            $table->string('auth_scheme', 32)->nullable();
            $table->string('token_jti', 100)->nullable();
            $table->string('client_id', 100)->nullable()->index();
            $table->boolean('token_found')->default(false);
            $table->boolean('token_revoked')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('merchant_id', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_auth_probes');
    }
};
