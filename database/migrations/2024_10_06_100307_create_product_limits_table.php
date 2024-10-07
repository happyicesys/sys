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
        Schema::create('product_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->date('date');
            $table->boolean('is_created_by_system')->default(false);
            $table->unsignedBigInteger('product_id')->index();
            $table->integer('qty');
            $table->date('setup_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_limits');
    }
};
