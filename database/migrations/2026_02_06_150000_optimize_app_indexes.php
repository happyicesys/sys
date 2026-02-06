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
        // 1. vend_transaction_items - vital for deletes and reports
        // Schema::table('vend_transaction_items', function (Blueprint $table) {
        //     $table->index('vend_transaction_id', 'idx_vti_transaction_id');
        // });

        // // 2. vend_channel_error_logs - vital for deletes and health reports
        // Schema::table('vend_channel_error_logs', function (Blueprint $table) {
        //     $table->index('vend_transaction_id', 'idx_vcel_transaction_id');
        //     // Adding created_at index for range queries in various health checks
        //     $table->index('created_at', 'idx_vcel_created_at');
        // });

        // // 3. vend_channels - vital for calculated columns in Customer/Vend Index
        // Schema::table('vend_channels', function (Blueprint $table) {
        //     // Supports: WHERE is_active = true AND capacity > 0 GROUP BY vend_id
        //     $table->index(['vend_id', 'is_active', 'capacity'], 'idx_vc_vid_active_cap');
        //     // Supports: where('sku_code', $sku)
        //     $table->index('sku_code', 'idx_vc_sku_code');
        // });

        // // 4. ops_job_items - vital for lastOpsJobItem relationships
        // Schema::table('ops_job_items', function (Blueprint $table) {
        //     // Supports: WHERE customer_id = ? ORDER BY created_at DESC
        //     $table->index(['customer_id', 'created_at'], 'idx_oji_cust_created');
        //     // Supports: WHERE status >= 3 ... GROUP BY customer_id
        //     $table->index(['status', 'customer_id'], 'idx_oji_status_cust');
        // });

        // // 5. ops_job_item_channels - vital for joins
        // Schema::table('ops_job_item_channels', function (Blueprint $table) {
        //     $table->index('ops_job_item_id', 'idx_ojic_item_id');
        //     $table->index('vend_channel_id', 'idx_ojic_channel_id');
        // });

        // // 6. ops_jobs - vital for date filtering in subqueries
        // Schema::table('ops_jobs', function (Blueprint $table) {
        //     $table->index('date', 'idx_oj_date');
        // });

        // // 7. vend_records - vital for historical data queries
        // Schema::table('vend_records', function (Blueprint $table) {
        //     // Often queried by opertor inside range
        //     $table->index(['operator_id', 'date'], 'idx_vr_op_date');
        // });

        // // 8. vend_channel_stock_events - vital for stockout metrics
        // Schema::table('vend_channel_stock_events', function (Blueprint $table) {
        //     $table->index(['occurred_at', 'event_type'], 'idx_vcse_occurred_type');
        //     $table->index('vend_channel_id', 'idx_vcse_channel_id');
        // });
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
