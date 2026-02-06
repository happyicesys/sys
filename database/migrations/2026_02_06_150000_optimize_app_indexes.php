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
        $indexes = [
            ['vend_transaction_items', 'idx_vti_transaction_id', 'vend_transaction_id'],
            ['vend_channel_error_logs', 'idx_vcel_transaction_id', 'vend_transaction_id'],
            ['vend_channel_error_logs', 'idx_vcel_created_at', 'created_at'],
            ['vend_channels', 'idx_vc_vid_active_cap', ['vend_id', 'is_active', 'capacity']],
            ['vend_channels', 'idx_vc_sku_code', 'sku_code'],
            ['ops_job_items', 'idx_oji_cust_created', ['customer_id', 'created_at']],
            ['ops_job_items', 'idx_oji_status_cust', ['status', 'customer_id']],
            ['ops_job_item_channels', 'idx_ojic_item_id', 'ops_job_item_id'],
            ['ops_job_item_channels', 'idx_ojic_channel_id', 'vend_channel_id'],
            ['ops_jobs', 'idx_oj_date', 'date'],
            ['vend_records', 'idx_vr_op_date', ['operator_id', 'date']],
            ['vend_channel_stock_events', 'idx_vcse_occurred_type', ['occurred_at', 'event_type']],
            ['vend_channel_stock_events', 'idx_vcse_channel_id', 'vend_channel_id'],
        ];

        foreach ($indexes as $idx) {
            $tableName = $idx[0];
            $indexName = $idx[1];
            $columns = $idx[2];

            if (!Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_vti_transaction_id');
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->dropIndex('idx_vcel_transaction_id');
            $table->dropIndex('idx_vcel_created_at');
        });

        Schema::table('vend_channels', function (Blueprint $table) {
            $table->dropIndex('idx_vc_vid_active_cap');
            $table->dropIndex('idx_vc_sku_code');
        });

        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropIndex('idx_oji_cust_created');
            $table->dropIndex('idx_oji_status_cust');
        });

        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->dropIndex('idx_ojic_item_id');
            $table->dropIndex('idx_ojic_channel_id');
        });

        Schema::table('ops_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_oj_date');
        });

        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropIndex('idx_vr_op_date');
        });

        Schema::table('vend_channel_stock_events', function (Blueprint $table) {
            $table->dropIndex('idx_vcse_occurred_type');
            $table->dropIndex('idx_vcse_channel_id');
        });
    }
};
