<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site lifecycle dates that drive the Summary commission engine.
 *
 *   active_date  — when the site's CURRENT active interval started. Commission
 *                  rows are generated from this date onward. Seeded to the
 *                  (floored) begin_date for existing sites; set when a user
 *                  flips status to Active with an Active Date.
 *   removed_date — when the site was Removed (status "Removed", formerly
 *                  "Pending"). Commission stops after this date; the removal
 *                  month is prorated. NULL while the site is active. Cleared
 *                  again when the site is re-activated.
 *
 * The existing customers.termination_date column is REPURPOSED as the auto-
 * captured "Inactive Date" (stamped when status flips to Inactive) — it is no
 * longer user-settable and no longer gates the commission calc. Renaming it was
 * avoided on purpose: it is referenced by CMS sync / Vend resources, so the
 * column name stays and only its UI label + semantics change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('active_date')->nullable()->after('begin_date');
            $table->date('removed_date')->nullable()->after('active_date');

            // Lets the aggregator's per-month inclusion gate resolve the active
            // window quickly (active_date <= period_end, removed_date >= month
            // start) without a full table scan.
            $table->index('active_date', 'customers_active_date_idx');
            $table->index('removed_date', 'customers_removed_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_active_date_idx');
            $table->dropIndex('customers_removed_date_idx');
            $table->dropColumn(['active_date', 'removed_date']);
        });
    }
};
