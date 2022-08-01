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
        Schema::create('vending_machines', function (Blueprint $table) {
            $table->id();
            $table->integer('code');
            $table->integer('serial_num')->nullable();
            $table->string('name')->nullable();
            $table->integer('temp')->nullable();
            $table->datetime('temp_updated_at')->nullable();
            $table->integer('coin_amount')->nullable();
            $table->integer('firmware_ver')->nullable();
            $table->boolean('is_door_open')->default(false);
            $table->boolean('is_sensor_normal')->default(false);
            $table->bigInteger('simcard_id')->nullable();
            $table->bigInteger('cashless_terminal_id')->nullable();
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
        Schema::dropIfExists('vending_machines');
    }
};
