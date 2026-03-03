<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // TEMPORARILY DISABLED FOR MIGRATION — remove the `return;` lines to re-enable
    public function up(): void
    {
        return;
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->index(['vend_id', 'transaction_datetime', 'amount'], 'idx_vtrans_optimal_sales');
        });
    }

    public function down(): void
    {
        return;
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_vtrans_optimal_sales');
        });
    }
};
