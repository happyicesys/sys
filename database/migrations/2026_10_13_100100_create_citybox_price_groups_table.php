<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CityBox price groups (OPS Pro "价格组管理"), mirrored from `price_group_list` (2026-10-13):
 * one row per group × product, as they send it. Kept even when the group name is not one of
 * our RP1–RP5 tiers; a row that drops out of their list keeps its history via `removed_at`.
 * Groups named RP1–RP5 are also projected onto selling_prices (CityboxPriceGroupSync).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citybox_price_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('group_name', 64);
            $table->unsignedBigInteger('citybox_product_id');
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->integer('price_cents');
            $table->string('img_url', 512)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'citybox_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citybox_price_groups');
    }
};
