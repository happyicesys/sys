<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settlement-level audit trail (who/when): entry_added, entry_removed, closed,
 * exported_cimb, exported_xlsx, marked_done, created, voided. Ticket-level history
 * still lives in refund_ticket_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_settlement_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('refund_payout_batch_id')->index();
            $table->bigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('action');
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_settlement_logs');
    }
};
