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
        Schema::create('product_mapping_vend_prefix', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_mapping_id')->index()->unsigned();
            $table->bigInteger('vend_prefix_id')->index()->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_mapping_vend_prefix');
    }
};
