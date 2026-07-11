<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refund settlements now use only two statuses: open / closed. Fold any legacy
 * `exported` / `done` rows into `closed` (exporting and marking-done no longer
 * change the settlement status).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('refund_payout_batches')
            ->where('is_settlement', true)
            ->whereIn('status', ['exported', 'done'])
            ->update(['status' => 'closed']);
    }

    public function down(): void
    {
        // One-way: the finer states aren't recoverable.
    }
};
