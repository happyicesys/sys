<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every push Zijia sends to /api/smart-freezer/zijia/sku/notify (a product approved, rejected or
 * withdrawn in their portal), kept whole — the payload is not final — with what mark1 did with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zijia_sku_notifications', function (Blueprint $table) {
            $table->id();
            $table->longText('raw_body')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('verified')->nullable();
            $table->string('merchant_goods_code', 64)->nullable()->index();
            $table->string('product_code', 64)->nullable();
            $table->string('sku_name', 255)->nullable();
            $table->string('audit_status', 32)->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('outcome', 32)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zijia_sku_notifications');
    }
};
