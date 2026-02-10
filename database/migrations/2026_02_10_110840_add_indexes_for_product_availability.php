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
        try {
            Schema::table('ops_job_item_channels', function (Blueprint $table) {
                // Compound index for filtering by product and joining to ops_job_items
                $table->index(['product_id', 'ops_job_item_id'], 'idx_ojic_prod_item');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('ops_job_items', function (Blueprint $table) {
                // Compound index for joining to ops_jobs, filtering by status, and joining from ops_job_item_channels
                $table->index(['ops_job_id', 'id', 'status'], 'idx_oji_job_id_status');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('ops_jobs', function (Blueprint $table) {
                // Compound index for filtering by date range and joining to ops_job_items
                $table->index(['date', 'id'], 'idx_oj_date_id');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('ops_job_item_channels', function (Blueprint $table) {
                $table->dropIndex('idx_ojic_prod_item');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->dropIndex('idx_oji_job_id_status');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('ops_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_oj_date_id');
            });
        } catch (\Exception $e) {
        }
    }
};
