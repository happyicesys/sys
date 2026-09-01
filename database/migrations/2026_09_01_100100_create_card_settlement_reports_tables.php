<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card-terminal settlement report ingestion (NETS MerchantConnect daily CSV
 * first; provider column keeps it open for other acquirers).
 *
 * One `card_settlement_reports` row per uploaded file (the file itself is a
 * polymorphic Attachment on the report). Every data line lands in
 * `card_settlement_rows`, which carries the match outcome against
 * vend_transactions:
 *
 *  - fingerprint is a sha1 over (provider, terminal, date, sequence, amount,
 *    time) and is UNIQUE — re-uploading the same file, or files with
 *    overlapping cutover windows (the NETS business day spans two calendar
 *    dates), marks the repeats duplicate instead of double-matching.
 *  - matched_vend_transaction_id is UNIQUE — one settlement line can claim a
 *    sale exactly once, across all reports ever uploaded.
 *  - time_is_partial: NETS files that were round-tripped through Excel lose
 *    the hour ("21:48:59" → "48:59.0"); such rows keep only mm:ss and match
 *    circularly within the hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_settlement_reports', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('nets');
            $table->string('merchant_account', 64)->nullable();
            $table->string('original_filename');
            $table->date('cutover_date')->nullable();
            $table->dateTime('report_generated_at')->nullable();
            $table->string('status', 20)->default('uploaded'); // uploaded / matching / review / synced / failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('purchase_rows')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('ambiguous_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('ignored_count')->default(0);
            $table->unsignedInteger('synced_count')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('matched_at')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->unsignedBigInteger('synced_by')->nullable();
            $table->timestamps();

            $table->index(['provider', 'cutover_date']);
            $table->index('status');
        });

        Schema::create('card_settlement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_settlement_report_id')
                ->constrained('card_settlement_reports')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->string('txn_type', 32);
            $table->string('product', 64)->nullable();
            $table->string('card_issuer', 64)->nullable();
            $table->string('terminal_id', 64);
            $table->date('transaction_date');
            $table->time('transaction_time')->nullable();
            $table->boolean('time_is_partial')->default(false);
            $table->integer('amount_cents');
            $table->string('sequence_no', 32)->nullable();
            $table->char('fingerprint', 40)->unique();
            $table->unsignedTinyInteger('status')->default(0); // CardSettlementRow::STATUS_*
            $table->unsignedBigInteger('vend_id')->nullable();
            $table->unsignedBigInteger('matched_vend_transaction_id')->nullable()->unique();
            $table->integer('match_time_delta')->nullable(); // seconds, txn - report
            $table->json('candidates_json')->nullable();
            $table->string('resolution_note')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['card_settlement_report_id', 'status']);
            $table->index('terminal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_settlement_rows');
        Schema::dropIfExists('card_settlement_reports');
    }
};
