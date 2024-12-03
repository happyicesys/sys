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
        Schema::create('apk_setting_vend', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('apk_setting_id');
            $table->unsignedBigInteger('vend_id');

            $table->unique(['apk_setting_id', 'vend_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apk_setting_vend');
    }
};
