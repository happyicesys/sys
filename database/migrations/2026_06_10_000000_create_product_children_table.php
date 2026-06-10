<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — flavours + weights defined PER PRODUCT (not per mapping).
 *
 * The blind parent (is_parent_sku) owns its flavour mix once, on the product
 * itself, so it is reused across every product mapping that binds the parent to
 * a channel — no need to re-declare the same children for each mapping.
 *
 * Replaces the earlier per-mapping `product_mapping_item_children` table, which
 * is dropped below (the feature is not in production yet).
 *
 *   weight_pct : 1..100; active siblings of one parent must sum to exactly 100.
 *
 * A child flavour MAY belong to multiple parents (unique is per parent+child).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_children')) {
            Schema::create('product_children', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_product_id'); // the housing (is_parent_sku)
                $table->unsignedBigInteger('child_product_id');  // the real flavour
                $table->unsignedTinyInteger('weight_pct')->default(0);
                $table->integer('sort')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['parent_product_id', 'child_product_id'], 'pc_parent_child_unique');
                $table->index('child_product_id', 'pc_child_idx');
                $table->index(['parent_product_id', 'is_active'], 'pc_parent_active_idx');

                $table->foreign('parent_product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('child_product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        // The per-mapping table is superseded by product_children.
        Schema::dropIfExists('product_mapping_item_children');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_children');
        // Note: product_mapping_item_children is intentionally not recreated here.
    }
};
