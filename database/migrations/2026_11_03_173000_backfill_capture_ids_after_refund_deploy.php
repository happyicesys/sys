<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Charges captured between the table migration and the code that records capture_txn_id: the
     * same read of the timeline, for rows still without one. Fills that new column only.
     */
    public function up(): void
    {
        (require __DIR__.'/2026_11_03_170000_create_card_refunds.php')->backfill();
    }

    public function down(): void {}
};
