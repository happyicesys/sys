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
        Schema::create('vend_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_refunded')->default(false);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('unit_cost')->nullable();
            $table->unsignedBigInteger('unit_cost_id')->nullable();
            $table->unsignedBigInteger('vend_channel_id');
            $table->string('vend_channel_code');
            $table->unsignedBigInteger('vend_channel_error_id')->nullable();
            $table->unsignedBigInteger('vend_transaction_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_transaction_items');
    }
};
