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
        Schema::create('campaign_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('apk_setting_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('value')->nullable();
            $table->unsignedInteger('promo_type')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_items');
    }
};
