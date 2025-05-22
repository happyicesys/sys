<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('vend_records', function (Blueprint $table) {
            // Composite index for operator_id, date, vend_id
            $table->index(['operator_id', 'date', 'vend_id'], 'idx_operator_date_vend');

            // Composite index for operator_id, year, month
            $table->index(['operator_id', 'year', 'month'], 'idx_operator_year_month');

            // Composite index for customer_id, operator_id, date
            $table->index(['customer_id', 'operator_id', 'date'], 'idx_customer_operator_date');

            // Optional: If filtering by vend_prefix_id is frequent
            $table->index(['vend_prefix_id', 'operator_id', 'date'], 'idx_prefix_operator_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropIndex('idx_operator_date_vend');
            $table->dropIndex('idx_operator_year_month');
            $table->dropIndex('idx_customer_operator_date');
            $table->dropIndex('idx_prefix_operator_date');
        });
    }
};
