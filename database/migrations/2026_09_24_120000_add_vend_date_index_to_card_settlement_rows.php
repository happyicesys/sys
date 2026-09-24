<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LateTradePairer learns each machine's board clock from its matched lines
 * around a day, and the matcher counts a terminal-day's matches on its bound
 * machine — both look lines up by (vend_id, transaction_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->index(['vend_id', 'transaction_date'], 'card_settlement_rows_vend_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->dropIndex('card_settlement_rows_vend_date_index');
        });
    }
};
