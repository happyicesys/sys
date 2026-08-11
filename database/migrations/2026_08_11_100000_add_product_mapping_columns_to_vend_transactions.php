<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the planogram that produced each sale.
 *
 *   vend_transactions.product_mapping_id
 *       The Product Mapping the vend was running at the moment of the sale
 *       (vends.product_mapping_id at TRADE / gateway paid-time). vends.
 *       product_mapping_id is *current* state and is rewritten on every
 *       changeover, so without this snapshot a historical row's planogram is
 *       unrecoverable once the vend is re-mapped.
 *
 *   vend_transactions.product_mapping_item_id
 *   vend_transaction_items.product_mapping_item_id
 *       The exact product_mapping_items row (channel_code -> product_id ->
 *       selling_price_id) that resolved the sale. Pins product AND price
 *       attribution even after the mapping's items are edited in place.
 *       Parent row carries it for single-channel transactions; multi-channel
 *       transactions carry null on the parent and one id per child item,
 *       mirroring how product_id already behaves.
 *
 * NULL semantics: NULL means "not captured" — every row written before this
 * migration, plus the FVM back-date synthetic rows (no channel, no known
 * historical mapping) and any vend with no mapping assigned. There is
 * deliberately NO backfill: mark1 keeps no audit trail of when a vend's
 * mapping changed, so stamping today's vends.product_mapping_id onto 4.6M
 * historical rows would manufacture wrong data that reads as authoritative.
 * Forward-only from deploy.
 *
 * Type: BIGINT UNSIGNED to match product_mappings.id / product_mapping_items.id
 * (and the stock_counts.product_mapping_id + refund_request_id precedent), so a
 * future index or FK needs no column change.
 *
 * Performance — vend_transactions is ~4.7M rows / 3.7GB data + 4.0GB index:
 *   - Both columns are nullable with a constant default and are APPENDED as the
 *     last columns, so MySQL 8.0 applies ALGORITHM=INSTANT: metadata-only, no
 *     table rebuild, no row backfill, no row-level lock. Runtime is milliseconds
 *     regardless of table size.
 *   - INSTANT is forced explicitly (same pattern as
 *     2026_05_25_000000_add_settlement_columns_to_vend_transactions) so the
 *     engine can never silently fall back to a COPY rebuild. If INSTANT is ever
 *     refused the migration fails loudly instead of locking the table for hours.
 *   - Both columns are added in ONE ALTER = one row-version bump, not two.
 *   - NO INDEX is created. An index build on this table is an INPLACE full scan
 *     of 4.7M rows (minutes of I/O, ~100-200MB extra on an already-4GB index)
 *     and nothing queries by mapping yet — reporting can reach it today via the
 *     already-indexed vends.product_mapping_id (869 rows). Add a targeted
 *     (product_mapping_id, transaction_datetime) index in its own migration when
 *     a real query needs it.
 *
 * The vend_transaction_items add (~302k rows) is trivially INSTANT too and is
 * kept in a separate statement so a failure on one table can't half-apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clauses = [];

        if (! Schema::hasColumn('vend_transactions', 'product_mapping_id')) {
            $clauses[] = 'ADD COLUMN `product_mapping_id` BIGINT UNSIGNED NULL DEFAULT NULL';
        }

        if (! Schema::hasColumn('vend_transactions', 'product_mapping_item_id')) {
            $clauses[] = 'ADD COLUMN `product_mapping_item_id` BIGINT UNSIGNED NULL DEFAULT NULL';
        }

        if (! empty($clauses)) {
            DB::statement(
                'ALTER TABLE `vend_transactions` '.implode(', ', $clauses).', ALGORITHM=INSTANT'
            );
        }

        if (! Schema::hasColumn('vend_transaction_items', 'product_mapping_item_id')) {
            DB::statement(
                'ALTER TABLE `vend_transaction_items` '
                .'ADD COLUMN `product_mapping_item_id` BIGINT UNSIGNED NULL DEFAULT NULL, ALGORITHM=INSTANT'
            );
        }
    }

    public function down(): void
    {
        // DROP COLUMN is INSTANT on MySQL 8.0.29+ and INPLACE on older 8.0.x.
        // The algorithm is deliberately not forced so the rollback works on any 8.0.x.
        Schema::table('vend_transactions', function ($table) {
            foreach (['product_mapping_item_id', 'product_mapping_id'] as $column) {
                if (Schema::hasColumn('vend_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('vend_transaction_items', function ($table) {
            if (Schema::hasColumn('vend_transaction_items', 'product_mapping_item_id')) {
                $table->dropColumn('product_mapping_item_id');
            }
        });
    }
};
