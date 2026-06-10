<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — virtual per-child ledger for OpsJob picking.
 *
 * A blind slot is ONE physical vend_channel (the parent housing), so the VMC
 * reports a single count for it and the flavours behind it have no channel of
 * their own. This table layers a per-flavour ledger on top of the parent's
 * ops_job_item_channels row WITHOUT touching the channel/VMC model:
 *
 *   to_pick_qty : allocator suggestion (Largest-Remainder of the slot's needed
 *                 qty across active, available, uncapped flavours)
 *   picked_qty  : what the driver actually picked for this flavour
 *   actual_qty  : what was loaded into the machine for this flavour
 *   weight_pct  : ratio snapshot at job time (audit / reproducibility)
 *
 * The parent channel row stays the source of truth for the machine-facing slot
 * count; children reconcile to it by ratio. Subtotals must count CHILDREN here
 * (not the parent) to avoid double counting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ops_job_item_channel_children')) {
            return;
        }

        Schema::create('ops_job_item_channel_children', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ops_job_item_channel_id'); // parent slot's channel row
            $table->unsignedBigInteger('child_product_id');
            $table->unsignedTinyInteger('weight_pct')->default(0); // snapshot
            $table->integer('to_pick_qty')->default(0);
            $table->integer('picked_qty')->default(0);
            $table->integer('actual_qty')->default(0);  // stock-in for this flavour
            $table->integer('picked_before_qty')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['ops_job_item_channel_id', 'child_product_id'], 'ojicc_slot_child_unique');
            $table->index('child_product_id', 'ojicc_child_idx');

            $table->foreign('ops_job_item_channel_id')
                ->references('id')->on('ops_job_item_channels')
                ->cascadeOnDelete();
            $table->foreign('child_product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_job_item_channel_children');
    }
};
