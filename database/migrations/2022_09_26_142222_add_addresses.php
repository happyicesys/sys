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
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('block_num')->nullable();
            $table->string('building')->nullable();
            $table->text('full_address')->nullable();
            $table->string('street_name')->nullable()->change();
            $table->string('unit_num')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('block_num');
            $table->dropColumn('building');
            $table->dropColumn('full_address');
        });
    }
};
