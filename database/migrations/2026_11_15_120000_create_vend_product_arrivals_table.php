<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When each SKU arrived on each machine, for the menu's NEW badge (Brian, 2026-11-15):
 * a SKU is NEW on a machine for 60 days after it first appears in that machine's product
 * mapping (App\Services\Menu\NewSkuBadge).
 *
 * Every pair already on a machine today is seeded as old (arrived_at 2000-01-01, source
 * "seed") in this same migration, so the fleet does not light up NEW on deploy day.
 * `php artisan menu:backfill-new-skus --apply` later dates the seeded pairs that really
 * arrived in the last 60 days from sales history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_product_arrivals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('product_id');
            // When the SKU (last) arrived on the machine. NEW until arrived_at + 60 days.
            $table->dateTime('arrived_at');
            // When the SKU was seen to leave the machine's mapping (NULL = on it). A SKU back after
            // more than 60 days away arrives again; back sooner keeps its arrival.
            $table->dateTime('left_at')->nullable();
            // seed | mapping | returned | backfill
            $table->string('source', 16);
            $table->timestamps();

            $table->unique(['vend_id', 'product_id']);
            $table->index('arrived_at');
        });

        // Seed: everything on every machine today is old. ON DUPLICATE KEY wins over any row a
        // menu read inserted in the moment between CREATE and this INSERT.
        DB::statement("
            INSERT INTO vend_product_arrivals (vend_id, product_id, arrived_at, left_at, source, created_at, updated_at)
            SELECT DISTINCT v.id, pmi.product_id, '2000-01-01 00:00:00', NULL, 'seed', NOW(), NOW()
            FROM vends v
            JOIN product_mapping_items pmi ON pmi.product_mapping_id = v.product_mapping_id
            ON DUPLICATE KEY UPDATE arrived_at = VALUES(arrived_at), left_at = NULL, source = VALUES(source)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_product_arrivals');
    }
};
