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
        Schema::create('vend_channel_stock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vend_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event_type', ['sold_out', 'restocked']);
            $table->unsignedSmallInteger('qty_before')->nullable();
            $table->unsignedSmallInteger('qty_after')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['vend_id', 'occurred_at']);
            $table->index(['vend_channel_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_channel_stock_events');
    }
};
