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
        Schema::table('vends', function (Blueprint $table) {
            $table->integer('led_matrix_panel_id')->nullable()->after('lcd_monitor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('led_matrix_panel_id');
        });
    }
};
