<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — remember WHEN a product became a blind housing.
 *
 * A product (e.g. BC001) may have existed for a long time as a normal SKU, used
 * in many past ops jobs. When the user later flips it to is_parent_sku, the blind
 * behaviour must only apply GOING FORWARD — ops jobs created before the flip keep
 * showing the product as the normal single row it was at the time.
 *
 *   is_parent_sku_since NULL  => legacy/unknown: blind applies to all its jobs
 *                                (so already-flipped test data is not disrupted).
 *   is_parent_sku_since set    => blind applies only to ops jobs created at/after
 *                                this moment. The Product model maintains it
 *                                automatically whenever is_parent_sku is toggled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_parent_sku_since')) {
                $table->timestamp('is_parent_sku_since')->nullable()->after('is_parent_sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_parent_sku_since')) {
                $table->dropColumn('is_parent_sku_since');
            }
        });
    }
};
