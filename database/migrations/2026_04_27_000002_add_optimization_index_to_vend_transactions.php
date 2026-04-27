<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            // Composite index leading with transaction_datetime for fast date-range scans
            // on the Sales Report Product tab queries (buildRawQuery / GpMetricsAggregator).
            $table->index(['transaction_datetime', 'amount', 'operator_id'], 'idx_vtrans_datetime_amount_operator');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_vtrans_datetime_amount_operator');
        });
    }
};
