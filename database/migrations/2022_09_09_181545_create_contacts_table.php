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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('alt_phone_num')->nullable();
            $table->bigInteger('alt_phone_country_id')->nullable();
            $table->string('email')->nullable();
            $table->morphs('modelable');
            $table->string('name');
            $table->string('phone_num')->nullable();
            $table->bigInteger('phone_country_id')->nullable();
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
        Schema::dropIfExists('contacts');
    }
};
