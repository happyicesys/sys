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
        Schema::create('cashless_terminals', function (Blueprint $table) {
            $table->id();
            $table->datetime('begin_date')->nullable();
            $table->string('code')->nullable();
            $table->bigInteger('created_by');
            $table->bigInteger('cashless_provider_id');
            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('cashless_terminals');
    }
};
