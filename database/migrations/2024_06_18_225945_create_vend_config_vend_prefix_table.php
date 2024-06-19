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
        Schema::create('vend_config_vend_prefix', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('vend_config_id')->unsigned()->index();
            $table->bigInteger('vend_prefix_id')->unsigned()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_config_vend_prefix');
    }
};
