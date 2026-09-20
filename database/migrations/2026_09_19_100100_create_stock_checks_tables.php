<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Count (UI name) = a spot check: a job-like stop in an ops job where the
 * driver counts a drawn sample of a machine's channels against the system qty.
 * Tables are `stock_checks` because `stock_counts` is the unrelated nightly
 * valuation snapshot behind the "Daily Stock Count" report — the two never
 * read or write each other. Plan: STOCK_CHECK_PLAN_2026-09-19.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code');
            $table->unsignedBigInteger('operator_id');
            $table->foreignId('ops_job_id')->constrained('ops_jobs')->cascadeOnDelete();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('sequence', 8, 2)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            // How the sample was drawn — kept so a re-draw repeats the same rule
            // and anyone can later see why these channels were picked.
            $table->boolean('is_random')->default(false);
            $table->unsignedSmallInteger('sample_size')->nullable();
            $table->json('product_filter')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('counted_at')->nullable();
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->dateTime('undo_counted_at')->nullable();
            $table->unsignedBigInteger('undo_counted_by')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->unsignedBigInteger('synced_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'code']);
            $table->index(['vend_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('stock_check_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_check_id')->constrained('stock_checks')->cascadeOnDelete();
            $table->unsignedBigInteger('vend_channel_id');
            $table->unsignedInteger('vend_channel_code');
            // Snapshots taken at the draw: a mapping swap later must not rewrite
            // what was asked to be counted.
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('amount')->default(0); // cents
            // Frozen at the moment the driver submits.
            $table->integer('system_qty')->nullable();
            $table->integer('counted_qty')->nullable();
            $table->integer('variance_qty')->nullable();
            $table->string('note', 500)->nullable();
            // "Sync" = apply the variance to vend_channels.qty.
            $table->dateTime('synced_at')->nullable();
            $table->unsignedBigInteger('synced_by')->nullable();
            $table->integer('qty_before_sync')->nullable();
            $table->integer('qty_after_sync')->nullable();
            $table->timestamps();

            $table->unique(['stock_check_id', 'vend_channel_id']);
            $table->index('vend_channel_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_check_channels');
        Schema::dropIfExists('stock_checks');
    }
};
