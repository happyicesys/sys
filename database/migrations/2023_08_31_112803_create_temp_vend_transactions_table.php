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
        Schema::create('temp_vend_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('ref_id');
            $table->string('vend_code');
            $table->datetime('transaction_datetime')->nullable();
            $table->string('transaction_datetime_ref')->nullable();
            $table->integer('channel_code');
            $table->integer('ref_payment_method_id');
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_vend_transactions');
    }
};
