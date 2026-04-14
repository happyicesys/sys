<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBindedAtToVendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dateTime('binded_at')->nullable()->after('product_mapping_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('binded_at');
        });
    }
}
