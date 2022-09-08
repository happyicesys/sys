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
        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->boolean('is_error_cleared')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->dropColumn('is_error_cleared');
        });
    }
};
