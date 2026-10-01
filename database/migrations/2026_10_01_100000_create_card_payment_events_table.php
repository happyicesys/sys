<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only timeline for the remote card terminal (Payrallel) trial: every
 * provider HTTP call with its raw answer and timing, every attempt state
 * change, terminal status changes, bindings and refused device requests.
 * Written only by CardTerminalEventLog; read with `payrallel:timeline`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->nullable();
            $table->unsignedBigInteger('remote_card_terminal_id')->nullable();
            $table->string('custom_order_id', 96)->nullable()->index();
            $table->string('event', 48);
            $table->string('level', 8)->default('info');
            $table->json('detail')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->dateTime('created_at', 3);

            $table->index(['vend_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_payment_events');
    }
};
