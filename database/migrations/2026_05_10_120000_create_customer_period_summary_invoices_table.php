<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the "Create API Invoice" calls fired off from the
 * Customer Management > Summary page.
 *
 * Mirrors the spirit of ops_job_items.cms_transaction_id but at a finer
 * grain: one row per (customer, period_start, period_end, invocation) so
 * the user can see / re-fire / audit invoicing per period range.
 *
 * Re-creation is intentionally allowed (the controller surfaces a confirm
 * dialog instead of blocking) — that's why there is NO unique constraint
 * on (customer_id, period_start, period_end). The "already created?" hint
 * on the UI is computed from the latest row for that triple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_period_summary_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            // Snapshot of the period range that was invoiced. Stored as
            // dates (not year_month) so aggregated period reports
            // ("Last 12 months") can be expressed too.
            $table->date('period_start');
            $table->date('period_end');
            // Snapshot of the contract type at the moment of invoicing.
            // Lets us answer "what did we bill them under?" later, even
            // if the customer's contract has since been changed.
            $table->string('contract_commission_type', 8)->nullable();

            // CMS round-trip metadata — populated once the queued job
            // resolves a successful response from /api/transactions/deals.
            $table->string('cms_transaction_id')->nullable();
            $table->datetime('cms_transaction_at')->nullable();
            $table->unsignedBigInteger('cms_transaction_by')->nullable();

            // Total amount invoiced in the smallest currency unit (cents),
            // mirroring the cents-storage convention used elsewhere in the
            // app. Useful for the UI badge so we don't have to recompute
            // from the report content service every page load.
            $table->bigInteger('total_amount_cents')->nullable();

            // Snapshot of the customer_period_summaries row + contract
            // values at the moment of invoicing. Once the invoice is
            // posted to CMS, the Customer Summary page renders this
            // frozen snapshot instead of the live row — that way a later
            // backfill of vend_transactions can never make the page show
            // numbers that disagree with what was actually invoiced.
            //
            // Shape: {
            //   sales_cents, gross_earning_cents, location_fees_cents,
            //   location_earning_cents, location_earning_rate,
            //   contract_commission_type, contract_commission_value,
            //   contract_commission_value2, contract_ps_term,
            //   active_days, month_days
            // }
            // JSON not dedicated columns: this is a display-only override,
            // sorts/filters still run on the live summary table.
            $table->json('summary_snapshot')->nullable();

            // Audit payload — what we sent and what we got back. JSON so
            // the schema doesn't need a migration if the CMS contract
            // evolves. Useful for incident response.
            $table->json('payload')->nullable();
            $table->json('response')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Look-up index: page renders pull "did this row already
            // invoice?" by (customer_id, period_start, period_end) — order
            // matters here so MySQL can skip the date range when the
            // customer has no invoices yet.
            $table->index(
                ['customer_id', 'period_start', 'period_end'],
                'cpsi_customer_period_idx'
            );
            // Secondary lookup for "show me this transaction" badges.
            $table->index('cms_transaction_id', 'cpsi_cms_transaction_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_period_summary_invoices');
    }
};
