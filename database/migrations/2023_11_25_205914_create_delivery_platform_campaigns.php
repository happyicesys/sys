<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_platform_campaigns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_product_mapping_id')->unsigned();
            $table->dateTime('datetime_from');
            $table->dateTime('datetime_to')->nullable();
            $table->text('desc')->nullable();
            $table->string('name');
            $table->string('platform_campaign_type')->nullable();
            $table->string('platform_campaign_scope')->nullable();
            $table->integer('platform_campaign_value')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_campaigns');
    }
};
