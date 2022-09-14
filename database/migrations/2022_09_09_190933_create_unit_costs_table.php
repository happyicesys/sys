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
        Schema::create('unit_costs', function (Blueprint $table) {
            $table->id();
            $table->integer('cost')->default(0);
            $table->datetime('date_from');
            $table->datetime('date_to')->nullable();
            $table->bigInteger('product_id');
            $table->bigInteger('profile_id')->nullable();
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
        Schema::dropIfExists('unit_costs');
    }
};
