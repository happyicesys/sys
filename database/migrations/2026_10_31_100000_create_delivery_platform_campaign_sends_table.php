<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grab Promotions, one page (Brian, 2026-10-31: "single source of truth … from Grab API
 * only"). Grab's own list is what runs; this table only logs what ConnectVend ASKED Grab
 * (create / change / end one store's campaign), who pressed it and what Grab answered —
 * Grab's list does not say which person made a campaign, and a refused send would
 * otherwise leave no trace. It is never read as what is live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_platform_campaign_sends', function (Blueprint $table) {
            $table->id();
            // One press of Send / Save / End: every store it went to shares it.
            $table->uuid('batch');
            // create / update / end
            $table->string('action', 16);
            // pending → sending → ok / failed
            $table->string('status', 16);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('delivery_platform_ref_number_id')->nullable();
            $table->string('merchant_id', 64);
            $table->unsignedBigInteger('delivery_platform_operator_id')->nullable();
            // Grab's id: given by Grab's answer to a create, the target of a change or end.
            $table->string('grab_campaign_id', 128)->nullable();
            $table->json('request_json')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('response_message', 1000)->nullable();
            $table->json('response_json')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index('batch', 'dp_campaign_sends_batch_index');
            $table->index('grab_campaign_id', 'dp_campaign_sends_grab_id_index');
            $table->index(['merchant_id', 'created_at'], 'dp_campaign_sends_merchant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_campaign_sends');
    }
};
