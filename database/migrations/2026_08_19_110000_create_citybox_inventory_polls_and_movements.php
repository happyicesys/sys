<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design §5b. Two tables because two questions:
 *  - citybox_inventory_polls   "what did we see"   — one row per device per poll
 *                              (~10 rows / 3 min). Pruned after ~90 days.
 *  - citybox_stock_movements   "what changed"      — one row per (device,
 *                              product) whose qty moved between two consecutive
 *                              polls. The ledger; kept forever; indexed by
 *                              product / vend / time so reports never re-read
 *                              snapshot_json.
 * No FKs to vends/products: append-only audit data must survive deletes and
 * remaps (product_id is DENORMALISED at write time for that reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_inventory_polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->index();
            $table->string('citybox_equipment_id', 64)->index();
            $table->timestamp('polled_at')->index();
            $table->boolean('online')->default(false);
            $table->smallInteger('device_status')->nullable();
            $table->unsignedSmallInteger('products_seen')->default(0);
            $table->integer('total_qty')->default(0);
            $table->json('snapshot_json')->nullable(); // {p90340: {qty, layer, active_price}, …}; NULL when the stock call failed
            $table->unsignedSmallInteger('movements_count')->default(0);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['vend_id', 'polled_at']);
        });

        Schema::create('citybox_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->index();
            $table->string('citybox_equipment_id', 64);
            $table->unsignedBigInteger('citybox_product_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index(); // mark1 product, denormalised
            $table->unsignedBigInteger('vend_channel_id')->nullable();
            $table->unsignedBigInteger('poll_id');       // citybox_inventory_polls.id that saw it
            $table->unsignedBigInteger('prev_poll_id')->nullable();
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->integer('delta');                     // after − before, signed
            $table->string('movement_type', 16)->index(); // sale | restock | correction | unknown
            $table->timestamp('occurred_between_start')->nullable();
            $table->timestamp('occurred_between_end')->index();
            $table->unsignedBigInteger('ops_job_item_id')->nullable()->index();
            $table->timestamps();

            $table->index(['vend_id', 'occurred_between_end']);
            $table->index(['product_id', 'occurred_between_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_stock_movements');
        Schema::dropIfExists('citybox_inventory_polls');
    }
};
