<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vend_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->index();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->boolean('is_returned')->default(false)->index();
            $table->integer('retries_count')->default(0);
            $table->timestamp('response_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_jobs');
    }
};
