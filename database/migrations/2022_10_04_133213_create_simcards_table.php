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
        Schema::create('simcards', function (Blueprint $table) {
            $table->id();
            $table->datetime('begin_date')->nullable();
            $table->string('code')->nullable();
            $table->bigInteger('created_by');
            $table->boolean('is_active')->default(true);
            $table->string('phone_number')->nullable();
            $table->bigInteger('telco_id');
            $table->datetime('termination_date')->nullable();
            $table->bigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('simcards');
    }
};
