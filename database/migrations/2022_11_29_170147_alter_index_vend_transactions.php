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
        Schema::table('vend_transactions', function (Blueprint $table) {
            // $table->datetime('transaction_datetime')->index()->change();
            $table->bigInteger('vend_id')->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            //
        });
    }
};
