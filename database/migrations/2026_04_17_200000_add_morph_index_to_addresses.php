<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index on addresses(modelable_type, type, modelable_id).
     *
     * The customer listing (VendController::indexCustomer) LEFT JOINs addresses
     * with three conditions:
     *
     *   addresses.modelable_id   = customers.id            ← join condition
     *   addresses.modelable_type = 'App\Models\Customer'   ← constant
     *   addresses.type           = 2                       ← constant
     *
     * Without a covering index, MySQL either does a full table scan of `addresses`
     * per customer row, or relies only on an index for `modelable_id` and then
     * filters the other two in memory.
     *
     * With (modelable_type, type, modelable_id):
     *   - modelable_type = constant → seeks to a narrow stripe of the index
     *   - type = constant → further narrows within that stripe
     *   - modelable_id → used for the equi-join; MySQL can probe one row per customer
     *
     * This converts a full-scan or under-indexed join into a three-part equality seek,
     * costing O(log n) per customer row instead of O(addresses).
     *
     * Note: Laravel's morphs() helper creates an index on (modelable_type, modelable_id)
     * by default, but that index doesn't include `type`, so MySQL still filters `type`
     * in memory after the index lookup. The new index covers all three conditions.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('addresses', 'idx_addresses_morph_type')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->index(
                    ['modelable_type', 'type', 'modelable_id'],
                    'idx_addresses_morph_type'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('addresses', 'idx_addresses_morph_type')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropIndex('idx_addresses_morph_type');
            });
        }
    }
};
