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
        Schema::create('vend_config_vend_config', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('vend_config_id')->unsigned();
            $table->bigInteger('compatible_vend_config_id')->unsigned();
            $table->timestamps();

            $table->unique(['vend_config_id', 'compatible_vend_config_id'], 'vend_config_compatible_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_config_vend_config');
    }
};
