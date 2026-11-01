<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grab Menu & Prices (Brian, 2026-11-01: "use back the original product mapping from the
 * machine … set which RP … or multiplier, or … custom pricing for each of the SKU").
 *
 * A store switched to the machine menu lists every SKU on its machine's slots; the price
 * comes from its operator's rule. Nothing here lists or removes a SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One pricing rule per operator. HIPL staff set any operator's; others only their own.
        Schema::create('grab_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id')->unique();
            // rp / rp_multiplier / custom (App\Services\Grab\Menu\GrabPricingMode)
            $table->string('pricing_mode', 16)->default('rp');
            // RP1–RP5 (selling_prices.type); read by rp and rp_multiplier.
            $table->unsignedTinyInteger('rp_type')->default(2);
            // ×1.10 = 11000; integer so the price stays integer cents.
            $table->unsignedInteger('multiplier_bp')->default(10000);
            // A multiplied price always rounds UP to this step (10 = 10¢, 100 = $1).
            $table->unsignedSmallInteger('round_up_cents')->default(10);
            // Grab Mart's top category {id, name} every sub-category sits under.
            $table->json('category_json')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Per operator and product: the Grab sub-category, and the price in custom mode.
        Schema::create('grab_menu_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id');
            $table->unsignedBigInteger('product_id');
            $table->json('sub_category_json')->nullable();
            // Integer cents; read only in custom mode.
            $table->unsignedInteger('custom_amount')->nullable();
            $table->unsignedInteger('previous_custom_amount')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'product_id'], 'grab_menu_products_operator_product_unique');
        });

        // The store sells its machine's slots (set) or its old Grab template (null).
        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->dateTime('menu_from_machine_at')->nullable()->after('status');
        });

        // A machine-menu order line is allocated to a slot directly, with no template row.
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_product_mapping_vend_channel_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->dropColumn('menu_from_machine_at');
        });
        Schema::dropIfExists('grab_menu_products');
        Schema::dropIfExists('grab_menu_settings');
    }
};
