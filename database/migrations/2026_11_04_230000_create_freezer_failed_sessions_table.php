<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a smart freezer reports when a paid session ends in an error (app 48+): the cart, the reason and
 * the payment. A record only: it books no sale, moves no stock and touches no money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freezer_failed_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->constrained('vends')->cascadeOnDelete();
            $table->string('session_ref', 64);
            $table->string('payment_method', 16);
            $table->string('payment_ref', 96)->nullable();
            $table->string('order_id', 64)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 64);
            $table->string('detail', 255)->nullable();
            $table->json('items');
            $table->boolean('is_test')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'session_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freezer_failed_sessions');
    }
};
