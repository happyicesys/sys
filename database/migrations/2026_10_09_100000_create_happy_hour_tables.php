<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Happy Hour campaigns (Brian, 2026-10-09): a scheduled percentage discount on the slowest-moving
 * SKUs of a smart freezer, one SKU per slot. `happy_hour_slots` is the materialised schedule and
 * the source of truth for money — each slot freezes its product and both prices, so a later edit
 * of the campaign never re-prices a sale that already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('happy_hour_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Bit 0 = Monday … bit 6 = Sunday (ISO day - 1); 127 = every day.
            $table->unsignedTinyInteger('days_mask')->default(127);
            $table->time('window_start');
            $table->time('window_end');
            $table->unsignedSmallInteger('slot_minutes')->default(60);
            $table->unsignedTinyInteger('sku_count')->default(4);
            $table->string('selection_rule', 32)->default('days_of_cover');
            $table->unsignedSmallInteger('lookback_days')->default(14);
            $table->unsignedTinyInteger('discount_pct');
            $table->unsignedTinyInteger('min_balance_pct')->default(15);
            $table->unsignedSmallInteger('min_qty')->default(2);
            $table->unsignedSmallInteger('price_step_cents')->default(10);
            $table->boolean('allow_below_cost')->default(false);
            $table->json('excluded_product_ids')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('happy_hour_campaign_vend', function (Blueprint $table) {
            $table->id();
            $table->foreignId('happy_hour_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vend_id')->index();
            $table->timestamps();
            $table->unique(['happy_hour_campaign_id', 'vend_id']);
        });

        Schema::create('happy_hour_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('happy_hour_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedBigInteger('product_id');
            $table->date('slot_date');
            $table->unsignedSmallInteger('position');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('original_price');
            $table->unsignedInteger('promo_price');
            $table->unsignedTinyInteger('discount_pct');
            $table->unsignedSmallInteger('rank');
            $table->integer('qty_at_pick')->nullable();
            $table->integer('capacity_at_pick')->nullable();
            $table->decimal('avg_daily_sales', 8, 3)->nullable();
            $table->decimal('days_of_cover', 8, 2)->nullable();
            $table->string('status', 16)->default('scheduled');
            $table->dateTime('checked_at')->nullable();
            $table->unsignedInteger('units_sold')->nullable();
            $table->unsignedInteger('revenue_cents')->nullable();
            $table->unsignedInteger('discount_cents')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'starts_at']);
            $table->index(['vend_id', 'ends_at']);
            $table->index(['happy_hour_campaign_id', 'slot_date']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happy_hour_slots');
        Schema::dropIfExists('happy_hour_campaign_vend');
        Schema::dropIfExists('happy_hour_campaigns');
    }
};
