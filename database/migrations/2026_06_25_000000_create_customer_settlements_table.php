<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site settlement ledger ("Payment History").
 *
 * Tracks what Happy Ice owes each site owner (the creditor) and the payments
 * we make against it — the same shape as a debtor Statement of Account
 * (DR = charge we owe, CR = payment we make, running BALANCE = outstanding).
 *
 * Sign convention on amount_cents:
 *   +ve  → a CHARGE that increases what we owe   (opening balance, monthly loc fee)
 *   -ve  → a PAYMENT / WAIVER that reduces it     (verified paid, waived, credit note)
 *
 * The running balance is always DERIVED (cumulative SUM ordered by
 * entry_date, id) — never stored — so it can't drift, mirroring how
 * Accumulated Vend Earning is live-derived elsewhere.
 *
 * Replaces the CMS-pulled customers.loc_fee_remarks "since …, owe …" text as
 * the source of truth for outstanding going forward (CMS sync is being
 * deprecated). The opening_balance row seeds the legacy figure once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_settlements')) {
            return;
        }

        Schema::create('customer_settlements', function (Blueprint $table) {
            $table->id();

            // Human-friendly, unique reference for each line (e.g. SET-000123)
            // so staff and site owners can refer to a specific entry. Auto-set
            // from the row id on create (see CustomerSettlement::booted()).
            $table->string('reference_no', 20)->nullable()->unique();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Denormalised operator for cheap scoping/filtering, mirrors
            // customer_period_summaries.operator_id. Nullable, no hard FK so a
            // legacy/operator-less customer never blocks a write.
            $table->unsignedBigInteger('operator_id')->nullable()->index();

            // Ledger date. Monthly charges land on the last day of their month;
            // payments use the actual payment date.
            $table->date('entry_date')->index();

            // The accounting month this row belongs to (YYYY-MM-01), matching
            // customer_period_summaries.year_month. Null for ad-hoc adjustments.
            $table->date('year_month')->nullable();

            // opening_balance | location_fee | payment | waiver | adjustment
            $table->string('entry_type', 20)->index();

            // SIGNED cents. +charge / -payment. bigInteger to match *_cents cols.
            $table->bigInteger('amount_cents')->default(0);

            // Short label (e.g. "Since 240531", "Location Fees May 2026", "Payment").
            $table->string('item', 255)->nullable();

            $table->text('remarks')->nullable();

            // Optional link back to the locked month a charge/payment came from.
            $table->foreignId('customer_period_summary_id')
                ->nullable()
                ->constrained('customer_period_summaries')
                ->nullOnDelete();

            // seed | cron | paid-action | manual — provenance for auditing.
            $table->string('source', 20)->default('manual')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Ledger read path: all rows for a site in chronological order.
            $table->index(['customer_id', 'entry_date', 'id'], 'cust_settle_chrono_idx');
            // Idempotency lookups for the monthly accrual (one loc_fee per
            // customer per month) and seeders.
            $table->index(['customer_id', 'year_month', 'entry_type'], 'cust_settle_month_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_settlements');
    }
};
