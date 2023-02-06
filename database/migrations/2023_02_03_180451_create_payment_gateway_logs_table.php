<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('order_id')->nullable();
            $table->bigInteger('vend_transaction_id');
            $table->bigInteger('operator_payment_gateway_id');
            $table->integer('amount')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
