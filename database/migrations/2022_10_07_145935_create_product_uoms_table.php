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
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id');
            $table->bigInteger('uom_id');
            $table->boolean('is_base_uom')->default(false);
            $table->boolean('is_transaction_uom')->default(false);
            $table->integer('value')->default(1);
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
        Schema::dropIfExists('product_uoms');
    }
};
