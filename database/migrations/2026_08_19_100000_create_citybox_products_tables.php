<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CityBox catalog mirror + link to mark1 products, and the per-run sync log.
 * Design: citybox/INVENTORY_DESIGN_2026-08-17.md §5.
 *
 * citybox_products — one row per CityBox SKU (their numeric `id`, the ONLY
 * stable key: their SKU-code field is 0 for our tenant). UNIQUE on it is the
 * no-duplication guarantee. product_id (mark1) is set by a human, nullable
 * until mapped; many CityBox ids may map to ONE mark1 product (their catalog
 * has duplicate names under different ids). Soft-delisted, never deleted.
 *
 * citybox_product_sync_logs — one row per run (scheduled/manual catalog, or
 * device-poll upsert) with counts + the ids that changed, so a flash message
 * and the audit trail read from the same row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('citybox_product_id')->unique();
            $table->unsignedBigInteger('product_id')->nullable()->index(); // FK products, human-set; no cascade
            $table->string('name');
            $table->string('sku_code', 64)->nullable(); // their product_id field — empty today, kept in case they populate it
            $table->string('img_url', 512)->nullable();
            $table->json('vision_imgs')->nullable();
            $table->string('volume', 64)->nullable();
            $table->string('unit', 32)->nullable();
            $table->unsignedInteger('class_id')->nullable();
            $table->string('class_name', 64)->nullable();
            $table->integer('last_price_cents')->nullable();  // last active price seen on any device — info only
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('last_seen_source', 16)->nullable(); // catalog | device
            $table->boolean('is_delisted')->default(false)->index();
            $table->timestamp('mapped_at')->nullable();
            $table->unsignedBigInteger('mapped_by')->nullable(); // users.id
            $table->timestamps();
        });

        Schema::create('citybox_product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 24)->index(); // catalog_scheduled | catalog_manual | device_poll
            $table->unsignedBigInteger('triggered_by')->nullable(); // users.id for manual runs
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('delisted')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->json('details_json')->nullable(); // {added:[ids], updated:[ids], delisted:[ids]}
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_product_sync_logs');
        Schema::dropIfExists('citybox_products');
    }
};
