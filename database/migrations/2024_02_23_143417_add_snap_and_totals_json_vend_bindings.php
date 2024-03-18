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
        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->json('snap_parameter_json')->nullable();
            $table->json('snap_vend_channels_json')->nullable();
            $table->json('snap_vend_channel_error_logs_json')->nullable();
            $table->json('snap_vend_status_json')->nullable();
            $table->json('totals_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->dropColumn('snap_parameter_json');
            $table->dropColumn('snap_vend_channels_json');
            $table->dropColumn('snap_vend_channel_error_logs_json');
            $table->dropColumn('snap_vend_status_json');
            $table->dropColumn('totals_json');
        });
    }
};
