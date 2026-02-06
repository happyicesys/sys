<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTempMonitoringStateToVendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->json('temp_monitoring_state')->nullable()->after('vend_temp_alert_json');
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
            $table->dropColumn('temp_monitoring_state');
        });
    }
}
