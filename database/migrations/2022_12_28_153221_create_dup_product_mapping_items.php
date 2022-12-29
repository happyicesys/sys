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
        Schema::dropIfExists('product_mapping_items');

        Schema::create('product_mapping_items', function (Blueprint $table) {
            $table->id();
            $table->string('channel_code');
            $table->bigInteger('product_id');
            $table->bigInteger('product_mapping_id');
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
        Schema::dropIfExists('product_mapping_items');
    }
};
