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
        Schema::create('vend_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('offline_after_minutes')->nullable();
            $table->unsignedInteger('power_restored_after_minutes')->nullable();
            $table->unsignedInteger('no_sales_after_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_alert_settings');
    }
};
