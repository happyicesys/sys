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
        Schema::create('payment_merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->string('mask_url')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->bigInteger('payment_merchant_id')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_merchants');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('payment_merchant_id');
        });
    }
};
