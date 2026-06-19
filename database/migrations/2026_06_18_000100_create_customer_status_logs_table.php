<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_status_logs
 *
 * Append-only audit trail of every Site status change. Powers the "Status
 * History" popup on the Customer Edit page (Status · Date · Changed By · When).
 *
 *   status_id   — the status the site was set TO (Customer::STATUS_*).
 *   status_date — the business-effective date the user entered for that change
 *                 (Active Date / Removed Date / auto Inactive Date). NULL for
 *                 statuses that carry no date (Potential / New).
 *   changed_by  — the user who made the change (null for system/seeder).
 *   source      — 'user' | 'system' | 'seeder'.
 *
 * NOTE: per the agreed design the Summary aggregator reads the LIVE
 * customers.active_date / removed_date (current-window only), NOT this log — the
 * log is purely the human-facing history. A future upgrade could make the
 * aggregator interval-aware off this table without changing anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_status_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->unsignedTinyInteger('status_id');
            $table->date('status_date')->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16)->default('user');

            $table->timestamps();

            // History lookup for a single site, newest first.
            $table->index(['customer_id', 'created_at'], 'csl_customer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_status_logs');
    }
};
