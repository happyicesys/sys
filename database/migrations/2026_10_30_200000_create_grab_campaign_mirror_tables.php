<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grab Promotions mirror (GRAB_REVAMP_PLAN §9.3, 2026-10-30): what Grab says is live,
 * read from GET /partner/v1/campaigns per store. mark1 never writes Grab from these
 * tables; they only record what Grab answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_platform_live_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('grab_campaign_id', 128)->unique('dp_live_campaigns_grab_id_unique');
            $table->string('merchant_id', 64);
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->nullable();
            $table->unsignedBigInteger('delivery_platform_operator_id')->nullable();
            // ongoing / upcoming, as Grab lists it.
            $table->string('state', 16);
            $table->string('created_by', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('discount_type', 32)->nullable();
            $table->string('scope_type', 16)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('campaign_json')->nullable();
            // Ours: the campaign item vend whose platform_ref_id is this campaign.
            $table->unsignedBigInteger('delivery_platform_campaign_item_vend_id')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            // Set when a good read of the store no longer lists it (ended, expired or deleted).
            $table->dateTime('gone_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'gone_at'], 'dp_live_campaigns_merchant_gone_index');
            $table->index(['delivery_platform_ref_number_id', 'gone_at'], 'dp_live_campaigns_ref_gone_index');
            $table->index('delivery_platform_campaign_item_vend_id', 'dp_live_campaigns_item_vend_index');
        });

        // One row per store: when Grab's campaign list was last read, and how it went.
        Schema::create('delivery_platform_campaign_reads', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_id', 64)->unique('dp_campaign_reads_merchant_unique');
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->nullable();
            $table->dateTime('last_read_at');
            $table->dateTime('last_ok_at')->nullable();
            // HTTP status of the last read; null = no answer.
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_message', 500)->nullable();
            $table->unsignedInteger('ongoing_count')->default(0);
            $table->unsignedInteger('upcoming_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_campaign_reads');
        Schema::dropIfExists('delivery_platform_live_campaigns');
    }
};
