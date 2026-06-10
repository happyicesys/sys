<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — children + weights, bound per ProductMapping.
 *
 * When a product_mapping_item points at a parent (is_parent_sku) product, the
 * real flavours that the housing dispenses are listed here, each with a weight.
 *
 *   weight_pct : integer 1..100; the ACTIVE siblings of one parent slot must
 *                sum to exactly 100 (validated in the request layer). It drives
 *                (a) the blended unit cost, (b) the OpsJob To-Pick split,
 *                (c) the Planning daily-sold split.
 *
 * Ratios live per-mapping on purpose: the same blind brand can house a different
 * flavour mix / weighting in a different mapping (different machines / sites).
 *
 * A child flavour MAY appear under multiple parents / multiple mappings — only
 * "once per slot" is enforced (the composite unique below).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_mapping_item_children')) {
            return;
        }

        Schema::create('product_mapping_item_children', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_mapping_item_id'); // the parent (housing) slot
            $table->unsignedBigInteger('child_product_id');        // the real flavour
            $table->unsignedTinyInteger('weight_pct')->default(0); // 1..100, active siblings sum to 100
            $table->integer('sort')->default(0);                   // display order + rounding tie-break
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_mapping_item_id', 'child_product_id'], 'pmic_slot_child_unique');
            // Cost fan-out: "given a child product, which parent slots use it?"
            $table->index('child_product_id', 'pmic_child_idx');
            $table->index(['product_mapping_item_id', 'is_active'], 'pmic_slot_active_idx');

            $table->foreign('product_mapping_item_id')
                ->references('id')->on('product_mapping_items')
                ->cascadeOnDelete();
            $table->foreign('child_product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mapping_item_children');
    }
};
