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
        Schema::create('vend_criteria_scores', function (Blueprint $table) {
            $table->id();
            $table->integer('value')->default(0);
            $table->bigInteger('vend_criteria_id');
            $table->bigInteger('vend_id');
            $table->integer('weightage')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_criteria_scores');
    }
};
