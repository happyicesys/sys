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
        Schema::create('machine_health_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->constrained()->onDelete('cascade');
            $table->string('event'); // machine_health_alert, machine_health_alert_dismissed
            $table->string('alert_type')->index(); // connectivity, rising_t1_trend, etc.
            $table->string('bucket')->index(); // > 12hr, Δ ≥ 1c, etc.
            $table->integer('severity')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['vend_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_health_histories');
    }
};
