<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `audit_pending_at` — when a sale's figures were put on hold for an outside audit.
 *
 * A smart-freezer sale paid on a T05 (remote card rail) is only provisional at door close: the
 * customer agreed a cart, but what they actually took is decided by the camera AI, and that
 * verdict decides the charge (AiCaptureDecision). Until it is in, the row must not be counted as
 * a sale (Brian, 2026-10-05: "the trade row cannot be calculate as sales total and qty total bcz
 * ai recognition havent done the actual audit").
 *
 * The EXCLUSION itself needs no new column — `settlement_status = 0` (pending: "paid, dispense
 * outcome not yet known") already means "not a sale yet" to every sales, revenue, GP and qty
 * aggregate, and to the nightly rollup. This column records WHY the row is pending, which
 * settlement_status cannot say: without it a freezer sale awaiting its audit is indistinguishable
 * from a gateway row still waiting for its TRADE, and SaleStatus would blank the Dispense cell as
 * "no TRADE" although the TRADE is in. It also drives the "Pending AI" cell on Sales Transactions
 * and tells ops how long a hold has been waiting.
 *
 * Null on every other row, so nothing is backfilled and no aggregate changes for existing data.
 * NOT indexed on purpose: the settle path finds its rows through `card_payment_intents`
 * (state = awaiting_ai, a small table), and the aggregates only read the column inside CASE
 * expressions, where an index on 5M mostly-NULL rows would cost space and buy nothing.
 *
 * Appended as the LAST column and nullable with no default, so MySQL 8.0 applies
 * ALGORITHM=INSTANT on the ~5M-row table (metadata only: no rebuild, no backfill, no lock).
 * Forced explicitly so a silent fallback to a COPY rebuild can never happen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vend_transactions', 'audit_pending_at')) {
            return; // already applied — safe to re-run
        }

        DB::statement('ALTER TABLE `vend_transactions` ADD COLUMN `audit_pending_at` TIMESTAMP NULL, ALGORITHM=INSTANT');
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function ($table) {
            if (Schema::hasColumn('vend_transactions', 'audit_pending_at')) {
                $table->dropColumn('audit_pending_at');
            }
        });
    }
};
