<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Snapshot the cashless terminal manufacturer (e.g. NYX, CASTLES) on the
     * transaction itself, instead of resolving it live from
     * vends.acb_vmc_pa_json->CSHL_MFG. Only populated for credit-card txns
     * (payment_method_id = 2). See VendTransactionService::createVendTransaction.
     *
     * Index leads with cashless_mfg + transaction_datetime so
     *   WHERE cashless_mfg = ? AND transaction_datetime BETWEEN ? AND ?
     * can use a single index range scan. Pure date-range queries continue to
     * use the existing idx_vtrans_datetime_amount_operator.
     */
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->string('cashless_mfg', 32)->nullable()->after('payment_method_id');
            $table->index(['cashless_mfg', 'transaction_datetime'], 'idx_vtrans_cashless_mfg_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_vtrans_cashless_mfg_datetime');
            $table->dropColumn('cashless_mfg');
        });
    }
};
