<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Grab store's own price rule (Brian, 2026-11-10: "make sure the user able to set the different price rule
 * for some stores"). A store with no row here follows its operator's rule (grab_menu_settings); a row replaces
 * that rule for this store only, on the machine menu. Written only by GrabStorePriceRuleService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grab_store_price_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->unique();
            // rp / rp_multiplier / custom (App\Services\Grab\Menu\GrabPricingMode), as grab_menu_settings.
            $table->string('pricing_mode', 16);
            $table->unsignedTinyInteger('rp_type');
            // ×1.15 = 11500; integer so the price stays integer cents.
            $table->unsignedInteger('multiplier_bp')->default(10000);
            $table->unsignedSmallInteger('round_up_cents')->default(10);
            // Why this store is priced differently, in staff's words ("quiet store", "mall rent").
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // The typed Grab price of one product at one store whose own rule is custom (integer cents).
        Schema::create('grab_store_custom_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_ref_number_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('previous_amount')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['delivery_platform_ref_number_id', 'product_id'], 'grab_store_custom_prices_store_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grab_store_custom_prices');
        Schema::dropIfExists('grab_store_price_rules');
    }
};
