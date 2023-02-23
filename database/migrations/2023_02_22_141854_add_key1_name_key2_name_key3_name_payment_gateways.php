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
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('key1_name')->nullable();
            $table->string('key2_name')->nullable();
            $table->string('key3_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn('key1_name');
            $table->dropColumn('key2_name');
            $table->dropColumn('key3_name');
        });
    }
};
