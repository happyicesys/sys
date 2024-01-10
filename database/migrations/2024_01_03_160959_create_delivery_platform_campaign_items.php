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
        Schema::create('delivery_platform_campaign_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_platform_campaign_id')->unsigned();
            $table->datetime('datetime_from');
            $table->datetime('datetime_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('min_amount')->default(0);
            $table->string('scope')->nullable();
            $table->integer('total_count')->nullable();
            $table->integer('total_count_per_user')->nullable();
            $table->string('type')->nullable();
            $table->integer('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_campaign_items');
    }
};
