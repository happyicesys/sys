<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            // For daysVendTransactions queries by vend_id
            $table->index(['vend_id', 'transaction_datetime'], 'idx_vend_transaction_datetime');

            // For daysVendTransactions queries by customer_id (via vend relationship)
            // This helps with customer->daysVendTransactions()
            $table->index(['transaction_datetime', 'vend_channel_error_id'], 'idx_datetime_error');
        });

        Schema::table('vend_records', function (Blueprint $table) {
            // For daysVendRecords queries by vend_id
            $table->index(['vend_id', 'date'], 'idx_vend_date');

            // For daysVendRecords queries by customer_id
            $table->index(['customer_id', 'date'], 'idx_customer_date');
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            // For queries filtering by vend_channel_id, created_at, and vend_transaction_id
            $table->index(['created_at', 'vend_transaction_id'], 'idx_created_txn');
        });

        Schema::table('vend_channels', function (Blueprint $table) {
            // For queries filtering by vend_id
            $table->index(['vend_id', 'is_active'], 'idx_vend_active');
        });

        Schema::table('product_limits', function (Blueprint $table) {
            // For resolveProductLimits query
            $table->index(['date', 'product_id', 'created_at'], 'idx_date_product_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_vend_transaction_datetime');
            $table->dropIndex('idx_datetime_error');
        });

        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropIndex('idx_vend_date');
            $table->dropIndex('idx_customer_date');
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->dropIndex('idx_created_txn');
        });

        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropIndex('idx_vend_active');
        });

        Schema::table('product_limits', function (Blueprint $table) {
            $table->dropIndex('idx_date_product_created');
        });
    }
};
