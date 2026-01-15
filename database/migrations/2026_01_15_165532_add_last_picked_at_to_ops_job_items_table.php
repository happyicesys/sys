<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->timestamp('last_picked_at')->nullable()->after('picked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropColumn('last_picked_at');
        });
    }
};
