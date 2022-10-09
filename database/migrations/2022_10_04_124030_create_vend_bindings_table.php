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
        Schema::create('vend_bindings', function (Blueprint $table) {
            $table->id();
            $table->datetime('begin_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->datetime('termination_date')->nullable();
            $table->bigInteger('customer_id');
            $table->bigInteger('vend_id');
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
        Schema::dropIfExists('vend_bindings');
    }
};
