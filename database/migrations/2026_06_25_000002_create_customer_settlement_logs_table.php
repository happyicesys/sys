<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_settlement_logs
 *
 * Append-only audit trail of every change to a site's settlement ledger
 * (Payment History): payments recorded, waivers, reversals, and edits to an
 * opening balance / adjustment. Powers the "Change history" panel in the
 * Payment-History popup and answers "who did what, when" for money matters.
 *
 *   action      — 'payment' | 'waiver' | 'payment_reversed' | 'edited'
 *                 | 'created' | 'deleted'
 *   entry_type  — snapshot of the settlement entry's type at the time.
 *   reference_no— snapshot of the entry ref (kept even if the entry is later
 *                 removed, so the history survives).
 *   old/new_amount_cents — before/after for an edit (null where N/A).
 *   note        — human description (e.g. "Opening balance edited").
 *   changed_by  — user who made the change (null for cron/seed).
 *   source      — 'user' | 'cron' | 'seed' | 'paid-action'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_settlement_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Nullable so a log row survives if its settlement entry is deleted.
            $table->foreignId('customer_settlement_id')->nullable()
                ->constrained('customer_settlements')->nullOnDelete();

            $table->string('reference_no', 20)->nullable();
            $table->string('action', 24);
            $table->string('entry_type', 20)->nullable();

            $table->bigInteger('old_amount_cents')->nullable();
            $table->bigInteger('new_amount_cents')->nullable();

            $table->string('note', 255)->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16)->default('user');

            $table->timestamps();

            // History lookup for a single site, newest first.
            $table->index(['customer_id', 'created_at'], 'cs_log_customer_created_idx');
            $table->index('customer_settlement_id', 'cs_log_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_settlement_logs');
    }
};
