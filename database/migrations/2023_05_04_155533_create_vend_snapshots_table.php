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
        Schema::create('vend_snapshots', function (Blueprint $table) {
            $table->id();
            $table->json('customer_json')->nullable();
            $table->bigInteger('operator_id')->nullable();
            $table->json('parameter_json')->nullable();
            $table->bigInteger('vend_id');
            $table->integer('vend_code');
            $table->json('vend_channels_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_snapshots');
    }
};
