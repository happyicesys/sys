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
        Schema::create('delivery_platform_order_complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_order_id')->nullable();
            $table->string('driver_phone_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_order_complaints');
    }
};
