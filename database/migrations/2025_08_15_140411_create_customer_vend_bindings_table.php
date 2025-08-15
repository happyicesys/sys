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
        Schema::create('customer_vend_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->nullable()
                ->onDelete('set null')
                ->index();
            $table->boolean('is_binding')->default(true);
            $table->foreignId('user_id')
                ->nullable()
                ->onDelete('set null')
                ->index();
            $table->foreignId('vend_id')
                ->nullable()
                ->onDelete('set null')
                ->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_vend_bindings');
    }
};
