<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who overwrote a SKU-stocked machine's on-hand qty by hand, and from what to what
 * (Setting/Edit "Stock Qty", 2026-09-30). One row per applied change; nothing is
 * written for a refused one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_channel_qty_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('vend_channel_id');
            $table->unsignedBigInteger('product_id')->nullable();
            // The position label at the time ("101A") — the row's code is relabelled on every sync.
            $table->string('channel_label', 16);
            $table->integer('qty_before');
            $table->integer('qty_after');
            // CityBox only: what their device_product read back after the submit (null = not read).
            $table->integer('supplier_qty_after')->nullable();
            $table->string('supplier_msg_id', 64)->nullable();
            $table->string('source', 32)->default('setting_edit');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['vend_channel_id', 'id']);
            $table->index(['vend_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_channel_qty_adjustments');
    }
};
