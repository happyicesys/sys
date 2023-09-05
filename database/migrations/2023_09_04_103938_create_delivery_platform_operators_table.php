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
        Schema::create('delivery_platform_operators', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_platform_id')->index();
            $table->bigInteger('operator_id')->index();
            $table->string('input1')->nullable();
            $table->string('input2')->nullable();
            $table->string('input3')->nullable();
            $table->string('type')->default('production');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_operators');
    }
};
