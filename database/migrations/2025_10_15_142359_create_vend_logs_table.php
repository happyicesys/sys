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
        Schema::create('vend_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->constrained()->cascadeOnDelete();
            $table->string('event', 100)->index();
            $table->string('subject')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_logs');
    }
};
