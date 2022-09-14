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
        Schema::create('profile_taxes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('profile_id');
            $table->integer('sequence')->nullable();
            $table->bigInteger('tax_id');
            $table->boolean('is_inclusive')->default(false);
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
        Schema::dropIfExists('profile_taxes');
    }
};
