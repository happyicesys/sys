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
        Schema::create('vend_criterias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('desc')->nullable();
            $table->boolean('has_sub_criteria')->default(false);
            $table->string('operator')->nullable();
            $table->json('options_json')->nullable();
            $table->integer('sequence')->nullable();
            $table->integer('value')->nullable();
            $table->integer('weightage')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_criterias');
    }
};


