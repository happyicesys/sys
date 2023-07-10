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
        Schema::create('vend_records', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->nullable()->index();
            $table->datetime('date')->index();
            $table->integer('day');
            $table->integer('failure_amount')->default(0);
            $table->integer('failure_count')->default(0);
            $table->integer('month');
            $table->string('monthname')->nullable();
            $table->bigInteger('operator_id')->nullable()->index();
            $table->integer('total_amount')->default(0);
            $table->integer('total_count')->default(0);
            $table->integer('vend_code');
            $table->bigInteger('vend_id')->index();
            $table->integer('year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_records');
    }
};
