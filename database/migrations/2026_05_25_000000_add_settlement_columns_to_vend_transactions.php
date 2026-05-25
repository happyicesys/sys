<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two flags that let payment-gateway payments live in vend_transactions
 * as first-class rows (the "merge Payment Gateway Transactions into Sales
 * Transactions" work).
 *
 *   is_found_in_transaction : has the machine reported this row back via TRADE?
 *                             Legacy + all non-gateway rows are TRADE-born, so
 *                             they default to 1 (true). Gateway rows are
 *                             pre-created at paid-time with 0, flipped to 1 when
 *                             the matching TRADE lands.
 *
 *   settlement_status       : 0 = pending  (paid, dispense outcome not yet known)
 *                             1 = refunded / void (NOT a sale)
 *                             2 = settled  (counts as a sale, then the normal
 *                                 error-code success logic still applies)
 *                             Legacy 4M rows default to 2 so existing success/
 *                             revenue aggregations are byte-for-byte unchanged.
 *
 * Performance: both are TINYINT, NOT NULL with a constant default, appended as
 * the LAST columns -> MySQL 8.0 applies ALGORITHM=INSTANT (metadata-only, no
 * table rebuild, no row backfill, no lock) even on the ~4M-row table. We force
 * INSTANT explicitly so a silent fallback to a COPY rebuild can never happen;
 * if the engine ever refuses INSTANT the migration fails loudly rather than
 * locking the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clauses = [];

        if (! Schema::hasColumn('vend_transactions', 'is_found_in_transaction')) {
            $clauses[] = 'ADD COLUMN `is_found_in_transaction` TINYINT(1) NOT NULL DEFAULT 1';
        }

        if (! Schema::hasColumn('vend_transactions', 'settlement_status')) {
            $clauses[] = 'ADD COLUMN `settlement_status` TINYINT UNSIGNED NOT NULL DEFAULT 2';
        }

        if (empty($clauses)) {
            return; // already applied — safe to re-run
        }

        DB::statement(
            'ALTER TABLE `vend_transactions` ' . implode(', ', $clauses) . ', ALGORITHM=INSTANT'
        );
    }

    public function down(): void
    {
        // DROP COLUMN is INSTANT on MySQL 8.0.29+; on older 8.0.x it is INPLACE.
        // We don't force the algorithm here so the rollback works on any 8.0.x.
        Schema::table('vend_transactions', function ($table) {
            foreach (['settlement_status', 'is_found_in_transaction'] as $column) {
                if (Schema::hasColumn('vend_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
