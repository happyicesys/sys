<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_scheduled_contracts
 *
 * A single PENDING future placement-contract change per customer. The user
 * enters the next contract's values on the Customer Edit page plus an
 * effective_date; the row sits here untouched until that date arrives.
 *
 * The daily `contract:apply-scheduled` command (runs just before
 * customer-summary:compute) finds rows whose effective_date <= today and
 * status = pending, then:
 *   - copies the values onto the live customers row,
 *   - appends a customer_contract_logs version stamped effective_from =
 *     effective_date 00:00 (so the existing mid-month segmentation in
 *     CustomerSummaryAggregator splits the month only when the date lands
 *     after the 1st — a 1st-of-month change stays a single whole-month row),
 *   - flips this row to status = applied with applied_at set.
 *
 * Only ONE pending row per customer is allowed (enforced in the controller).
 * Applied / cancelled rows are kept as an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_scheduled_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // The day this contract should take effect. Stored as a plain date;
            // the apply command stamps effective_from at startOfDay of it.
            $table->date('effective_date');

            // pending = waiting for its date | applied = already taken effect
            // | cancelled = removed by a user before it applied.
            $table->string('status', 16)->default('pending');
            $table->dateTime('applied_at')->nullable();

            // Snapshot of the future contract values (mirrors the contract_*
            // columns on customers / customer_contract_logs).
            $table->string('contract_commission_type', 8)->nullable();
            $table->decimal('contract_commission_value', 10, 2)->nullable();
            $table->decimal('contract_commission_value2', 10, 2)->nullable();
            $table->decimal('contract_ps_term', 5, 2)->nullable();
            $table->boolean('is_external_subsidize')->default(false);
            $table->decimal('external_subsidize_amount', 10, 2)->nullable();
            $table->date('contract_from')->nullable();
            $table->date('contract_until')->nullable();
            $table->boolean('contract_auto_renewal')->default(false);
            $table->string('contract_notice_period')->nullable();
            $table->text('contract_remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Drives both the "is there a pending one?" lookup (controller +
            // Edit prop) and the daily apply sweep (status + effective_date).
            $table->index(['customer_id', 'status'], 'csc_customer_status_idx');
            $table->index(['status', 'effective_date'], 'csc_status_eff_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_scheduled_contracts');
    }
};
