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
        Schema::create('vend_channel_records', function (Blueprint $table) {
            $table->id();
            $table->json('after_data_json')->nullable();
            $table->datetime('after_data_created_at')->nullable()->index();
            $table->json('before_data_json');
            $table->datetime('before_data_created_at')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_channel_records');
    }
};
