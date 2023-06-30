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
        Schema::create('vend_sub_criterias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('desc')->nullable();
            $table->string('operator')->nullable();
            $table->string('value')->nullable();
            $table->json('options_json')->nullable();
            $table->integer('sequence')->nullable();
            $table->integer('weightage')->nullable();
            $table->bigInteger('vend_criteria_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_sub_criterias');
    }
};
