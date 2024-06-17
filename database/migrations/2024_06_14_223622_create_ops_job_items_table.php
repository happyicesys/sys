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
        Schema::create('ops_job_items', function (Blueprint $table) {
            $table->id();
            $table->integer('cash_amount')->nullable();
            $table->integer('cashless_amount')->nullable();
            $table->json('channels_json')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->bigInteger('completed_by')->unsigned()->nullable();
            $table->bigInteger('customer_id')->unsigned()->index();
            $table->text('notes')->nullable();
            $table->bigInteger('ops_job_id')->unsigned()->index();
            $table->integer('sequence')->nullable();
            $table->integer('status')->default(1);
            $table->datetime('picked_at')->nullable();
            $table->bigInteger('picked_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned();
            $table->bigInteger('vend_id')->unsigned()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_job_items');
    }
};
