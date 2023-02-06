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
        Schema::create('operator_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('operator_id');
            $table->bigInteger('payment_gateway_id');
            $table->string('key1')->nullable();
            $table->string('key2')->nullable();
            $table->string('type')->default('sandbox');
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
        Schema::dropIfExists('operator_payment_gateways');
    }
};
