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
        Schema::create('vend_temp_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->unsignedTinyInteger('temp_type');
            $table->string('period_type', 20);
            $table->string('period_key', 64);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->integer('min_temp_value')->nullable();
            $table->integer('max_temp_value')->nullable();
            $table->unsignedInteger('reading_count')->default(0);
            $table->unsignedSmallInteger('days_covered')->default(0);
            $table->timestamp('min_temp_recorded_at')->nullable();
            $table->timestamp('max_temp_recorded_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'temp_type', 'period_type', 'period_key'], 'vend_temp_metrics_unique');
            $table->foreign('vend_id')
                ->references('id')
                ->on('vends')
                ->cascadeOnDelete();
            $table->index(['period_type', 'period_start'], 'vend_temp_metrics_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_temp_metrics');
    }
};
