<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->datetime('begin_date')->nullable();
            $table->boolean('is_testing')->default(false);
            $table->datetime('termination_date')->nullable();
            // $table->json('totals_json')->nullable();
            $table->json('snap_parameter_json')->nullable();
            $table->json('snap_vend_channels_json')->nullable();
            $table->json('snap_vend_channel_error_logs_json')->nullable();
            $table->json('snap_vend_status_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('begin_date');
            $table->dropColumn('is_testing');
            $table->dropColumn('termination_date');
            // $table->dropColumn('totals_json');
            $table->dropColumn('snap_parameter_json');
            $table->dropColumn('snap_vend_channels_json');
            $table->dropColumn('snap_vend_channel_error_logs_json');
            $table->dropColumn('snap_vend_status_json');
        });
    }
};
