<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — parent (housing) flag.
 *
 * A product with is_parent_sku = true is a "housing": the customer/machine only
 * sees this SKU (e.g. a blind/mystery ice cream), but it holds NO real inventory
 * of its own. The real flavours are bound as children inside a ProductMapping
 * (see product_mapping_item_children). Any product can be a housing — nothing is
 * keyed off the product code.
 *
 * Default false => every existing product keeps its exact current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_parent_sku')) {
                $table->boolean('is_parent_sku')->default(false)->after('is_inventory');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_parent_sku')) {
                $table->dropColumn('is_parent_sku');
            }
        });
    }
};
