<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * vend_channels: a one-letter position suffix for SKU-stocked machines.
 *
 * Smart Freezer and Smart Chiller stock is keyed by SKU (product_id) since
 * 2026-09-22; the channel code is only where the driver puts the SKU, and one
 * number may now carry several SKUs — 101A, 101B, 101C (Brian). `code` stays
 * an int so every layer/basket grouping, range check and claw exclusion is
 * untouched; the letter lives here.
 *
 * The unique guard from 2026-08-26 (duplicate rows inflated a machine's stock)
 * must survive: a NULL-able column inside a composite unique index would let
 * duplicates back in (MySQL treats NULLs as distinct), so the index uses a
 * stored generated `suffix_key` that maps NULL to '' — vending rows keep the
 * exact (vend_id, code) guard, SKU-stocked rows add the letter.
 *
 * Raw MySQL on purpose: generated columns are MySQL syntax and every environment
 * of this app (prod, testing) is MySQL 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vend_channels
            ADD COLUMN suffix CHAR(1) NULL AFTER code,
            ADD COLUMN suffix_key CHAR(1) AS (COALESCE(suffix, '')) STORED NOT NULL AFTER suffix");

        DB::statement('ALTER TABLE vend_channels
            DROP INDEX vend_channels_vend_id_code_unique,
            ADD UNIQUE INDEX vend_channels_vend_id_code_suffix_unique (vend_id, code, suffix_key)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vend_channels
            DROP INDEX vend_channels_vend_id_code_suffix_unique,
            ADD UNIQUE INDEX vend_channels_vend_id_code_unique (vend_id, code)');

        DB::statement('ALTER TABLE vend_channels DROP COLUMN suffix_key, DROP COLUMN suffix');
    }
};
