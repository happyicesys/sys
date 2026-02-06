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
        Schema::create('vend_smart_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type');
            $table->unsignedTinyInteger('severity')->default(1);
            $table->json('meta_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vend_id', 'is_active']);
            $table->index(['alert_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_smart_alerts');
    }
};
