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
        Schema::create('export_job_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_job_id');
            $table->integer('chunk_index');
            $table->string('filename')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_job_chunks');
    }
};
