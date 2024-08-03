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
            $table->integer('modem_type_id')->nullable();
            $table->integer('menu_frame_id')->nullable();
            $table->integer('claw_machine_body_id')->nullable();
            $table->integer('claw_machine_board_id')->nullable();
            $table->integer('lcd_monitor_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('modem_type_id');
            $table->dropColumn('menu_frame_id');
            $table->dropColumn('claw_machine_body_id');
            $table->dropColumn('claw_machine_board_id');
            $table->dropColumn('lcd_monitor_id');
        });
    }
};
