<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Daily snapshot of active vend-channel counts per product.
     *
     * One row per (product_id, date):
     *   channel_count = number of vend_channels where is_active=true
     *                   on active, non-disposed vends for that product.
     *
     * year / month / day are stored denormalised so range filters on
     * "Last Month", "This Month", etc. can hit a compact index without
     * a DATE() function scan.
     */
    public function up(): void
    {
        Schema::create('product_vend_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('channel_count')->default(0);
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->timestamps();

            // One snapshot per product per calendar day
            $table->unique(['product_id', 'date'], 'pvc_product_date_unique');

            // Covering index for the most common query pattern:
            // "SUM(channel_count) WHERE product_id IN (...) AND date BETWEEN ? AND ?"
            $table->index(['product_id', 'date', 'channel_count'], 'pvc_product_date_count_idx');

            // Year/month index for "Last Month" / "This Month" style filters
            $table->index(['year', 'month', 'product_id', 'channel_count'], 'pvc_year_month_product_idx');

            // Plain date index for cross-product date-range queries
            $table->index('date', 'pvc_date_idx');

            $table->foreign('product_id', 'pvc_product_fk')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_vend_channels');
    }
};
