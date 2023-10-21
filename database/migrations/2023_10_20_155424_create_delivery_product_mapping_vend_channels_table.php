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
        Schema::create('delivery_product_mapping_vend_channels', function (Blueprint $table) {
            $table->id();
            $table->integer('amount')->default(0);
            $table->integer('order_qty')->nullable();
            $table->unsignedBigInteger('delivery_product_mapping_vend_id');
            $table->boolean('is_active')->default(true);
            $table->integer('qty')->nullable();
            $table->integer('reserved_percent')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->string('vend_channel_code');
            $table->unsignedBigInteger('vend_channel_id')->index();
            $table->string('vend_code');
            $table->unsignedBigInteger('vend_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_product_mapping_vend_channels');
    }
};
