<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloud-commanded card terminals (Payrallel first) for the smart freezer.
 *
 * remote_card_terminals: one row per machine that sells through a remote
 * terminal, holding that terminal's own access token (encrypted cast).
 * Deliberately separate from card_terminal_units, which models NETS TIDs
 * matched against the NETS settlement CSV — a different money path.
 *
 * card_payment_intents: one row per card attempt. Keyed by the device's
 * reference (SF<millis>), charged at the provider under custom_order_id, so
 * every provider answer maps back to exactly one attempt without heuristics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_card_terminals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->unique();
            $table->string('provider', 32);
            $table->string('label', 64)->nullable();
            $table->text('access_token');
            $table->boolean('is_active')->default(true);
            $table->boolean('last_online')->nullable();
            $table->string('last_state', 32)->nullable();
            $table->dateTime('last_status_at')->nullable();
            $table->timestamps();

            $table->foreign('vend_id')->references('id')->on('vends')->cascadeOnDelete();
        });

        Schema::create('card_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('remote_card_terminal_id');
            $table->string('provider', 32);
            $table->string('reference', 64);
            $table->string('custom_order_id', 96)->unique();
            $table->string('mode', 16);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('captured_cents')->nullable();
            $table->string('state', 24)->index();
            $table->string('provider_status', 32)->nullable();
            $table->string('provider_txn_id', 96)->nullable();
            $table->string('payment_method', 48)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->unsignedSmallInteger('query_count')->default(0);
            $table->dateTime('last_queried_at', 3)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('cancel_requested_at')->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->json('last_response')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'reference']);
            $table->foreign('vend_id')->references('id')->on('vends')->cascadeOnDelete();
            $table->foreign('remote_card_terminal_id')->references('id')->on('remote_card_terminals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_payment_intents');
        Schema::dropIfExists('remote_card_terminals');
    }
};
