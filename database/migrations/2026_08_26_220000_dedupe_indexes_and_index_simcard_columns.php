<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index hygiene pass, prompted by the 2026-08-26 Operation Dashboard slowdown
 * investigation (the slowdown itself was the extra simcards/telcos joins,
 * fixed in 215820a168 — this migration is the wider sweep that came out of
 * auditing prod's indexes afterwards).
 *
 * ADDS — two columns the new "SimCard Package" surfaces filter on had NO
 * index at all [verified against prod information_schema 2026-08-26]:
 *
 *  - vends.simcard_id     HasFilter's package filter runs
 *                         `vends.simcard_id IN (subquery)`, and
 *                         SimcardController's vend_code sort runs a correlated
 *                         `WHERE simcard_id = simcards.id` per simcard row.
 *  - simcards.telco_id    the filter's inner subquery
 *                         (`SELECT id FROM simcards WHERE telco_id IN …`) and
 *                         the SimCard index page's telco grouping.
 *
 * DROPS — exact duplicates and strict left-prefixes of a wider index, minted
 * by successive performance-index migrations that didn't check what already
 * existed. Every one was verified non-unique on prod, unreferenced by any
 * IndexHint / raw FORCE INDEX call, and covered by a surviving index whose
 * leftmost columns match (so FK lookup support is preserved). Each is pure
 * write amplification — vends and vend_channels are written on every machine
 * report, vend_channel_error_logs on every error report.
 *
 *   vends:                    vends_code_index          = idx_vends_code
 *                             vends_customer_id_index   = idx_vends_customer_id
 *                             vends_is_testing_index    = idx_vends_is_testing
 *   vend_channels:            idx_vend_id_code          = unique (vend_id, code)
 *                             vend_channels_vend_id_index ⊂ the unique
 *   vend_channel_error_logs:  *_created_at_index        = idx_vcel_created_at
 *                             *_vend_transaction_id_index = idx_vcel_transaction_id
 *                             *_vend_channel_id_index   ⊂ idx_vcel_channel_created
 *                             *_vend_channel_error_id_index ⊂ idx_vcel_error_created
 *   ops_job_items:            ops_job_items_customer_id_index ⊂ four
 *                             customer_id-leading composites
 *
 * Every add and drop is guarded with hasIndex, so a database built fresh from
 * the migration chain (test DBs — some of these names never co-exist there)
 * and prod (which has all of them) both converge without error.
 */
return new class extends Migration
{
    private const ADDS = [
        // table => [column, index name]
        ['vends', 'simcard_id', 'idx_vends_simcard_id'],
        ['simcards', 'telco_id', 'idx_simcards_telco_id'],
    ];

    /** table => [index => column list to recreate on down()] */
    private const DROPS = [
        'vends' => [
            'vends_code_index' => ['code'],
            'vends_customer_id_index' => ['customer_id'],
            'vends_is_testing_index' => ['is_testing'],
        ],
        'vend_channels' => [
            'idx_vend_id_code' => ['vend_id', 'code'],
            'vend_channels_vend_id_index' => ['vend_id'],
        ],
        'vend_channel_error_logs' => [
            'vend_channel_error_logs_created_at_index' => ['created_at'],
            'vend_channel_error_logs_vend_transaction_id_index' => ['vend_transaction_id'],
            'vend_channel_error_logs_vend_channel_id_index' => ['vend_channel_id'],
            'vend_channel_error_logs_vend_channel_error_id_index' => ['vend_channel_error_id'],
        ],
        'ops_job_items' => [
            'ops_job_items_customer_id_index' => ['customer_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::ADDS as [$table, $column, $index]) {
            if (Schema::hasColumn($table, $column) && ! Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($column, $index));
            }
        }

        foreach (self::DROPS as $table => $indexes) {
            foreach (array_keys($indexes) as $index) {
                if (Schema::hasIndex($table, $index)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::ADDS as [$table, , $index]) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }

        foreach (self::DROPS as $table => $indexes) {
            foreach ($indexes as $index => $columns) {
                if (! Schema::hasIndex($table, $index)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $index));
                }
            }
        }
    }
};
