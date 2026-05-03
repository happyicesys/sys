<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates vend_product_records — a product-level daily aggregation table.
     *
     * Grouping key: (vend_id, customer_id, product_id, date)
     * One row per machine × customer × product × day.
     *
     * Compared to vend_records (machine-level), this table stores an extra
     * product dimension so the dashboard can filter/search by product name,
     * product code, category, and sub-category.
     */
    public function up(): void
    {
        Schema::create('vend_product_records', function (Blueprint $table) {
            $table->id();

            // ── Date dimensions ──────────────────────────────────────────────
            $table->date('date');
            $table->unsignedTinyInteger('day');
            $table->unsignedTinyInteger('month');
            $table->string('monthname');
            $table->unsignedSmallInteger('year');

            // ── Machine dimensions ───────────────────────────────────────────
            $table->unsignedBigInteger('vend_id');
            $table->string('vend_code')->nullable();
            $table->unsignedBigInteger('vend_prefix_id')->nullable();
            $table->unsignedBigInteger('vend_model_id')->nullable();

            // ── Customer / Operator / Location ───────────────────────────────
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->unsignedBigInteger('location_type_id')->nullable();

            // ── Product dimensions (denormalised for fast filtering) ──────────
            $table->unsignedBigInteger('product_id');
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->unsignedBigInteger('category_group_id')->nullable();
            $table->string('category_group_name')->nullable();
            $table->unsignedBigInteger('product_sub_category_id')->nullable();
            $table->string('product_sub_category_name')->nullable();

            // ── Success metrics ───────────────────────────────────────────────
            $table->bigInteger('total_amount')->default(0);       // dispensed revenue (cents)
            $table->bigInteger('total_count')->default(0);        // dispensed qty
            $table->bigInteger('all_total_count')->default(0);    // attempted qty (success + fail)
            $table->bigInteger('revenue')->default(0);            // revenue (cents)
            $table->bigInteger('gross_profit')->default(0);       // GP (cents)

            // ── Failure metrics ───────────────────────────────────────────────
            $table->bigInteger('error_count')->default(0);
            $table->bigInteger('failure_count')->default(0);
            $table->bigInteger('failure_amount')->default(0);

            // ── Online-channel metrics ────────────────────────────────────────
            $table->bigInteger('online_success_amount')->default(0);
            $table->bigInteger('online_success_count')->default(0);
            $table->bigInteger('online_failure_amount')->default(0);
            $table->bigInteger('online_failure_count')->default(0);

            $table->timestamps();

            // ── Unique record per machine × customer × product × day ─────────
            $table->unique(['vend_id', 'customer_id', 'product_id', 'date'], 'uq_vpr_vend_customer_product_date');

            // ── Covering index for date-range dashboard queries ───────────────
            // Mirrors idx_operator_date_vend on vend_records
            $table->index(['operator_id', 'date', 'product_id'], 'idx_vpr_operator_date_product');

            // ── Covering index for monthly aggregation queries ────────────────
            $table->index(
                ['operator_id', 'year', 'month', 'product_id', 'vend_id', 'total_amount', 'total_count'],
                'idx_vpr_monthly_summary'
            );

            // ── Individual filter indexes ─────────────────────────────────────
            $table->index('product_id',              'idx_vpr_product_id');
            $table->index('product_code',            'idx_vpr_product_code');
            $table->index('category_id',             'idx_vpr_category_id');
            $table->index('category_group_id',       'idx_vpr_category_group_id');
            $table->index('product_sub_category_id', 'idx_vpr_product_sub_category_id');
            $table->index('vend_id',                 'idx_vpr_vend_id');
            $table->index('customer_id',             'idx_vpr_customer_id');
            $table->index('operator_id',             'idx_vpr_operator_id');
            $table->index('location_type_id',        'idx_vpr_location_type_id');
            $table->index('vend_prefix_id',          'idx_vpr_vend_prefix_id');
            $table->index('vend_model_id',           'idx_vpr_vend_model_id');
            $table->index('date',                    'idx_vpr_date');
            $table->index('year',                    'idx_vpr_year');
            $table->index('month',                   'idx_vpr_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_product_records');
    }
};
