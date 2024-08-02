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
        Schema::create('ops_job_item_channels', function (Blueprint $table) {
            $table->id();
            $table->integer('actual_qty')->nullable();
            $table->integer('capacity')->default(0);
            $table->bigInteger('ops_job_id')->unsigned()->index();
            $table->bigInteger('ops_job_item_id')->unsigned()->index();
            $table->integer('picked_qty')->nullable();
            $table->bigInteger('product_id')->unsigned()->index();
            $table->bigInteger('vend_channel_id')->unsigned()->index();
            $table->integer('vend_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_job_item_channels');
    }
};
